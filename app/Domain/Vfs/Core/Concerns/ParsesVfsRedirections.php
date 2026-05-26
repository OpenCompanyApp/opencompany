<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * Extracts shell redirection operators from tokenized VFS commands.
 *
 * This is intentionally separate from tokenization: redirection behavior is
 * security-sensitive because it can turn read output into workspace writes.
 */
trait ParsesVfsRedirections
{
    /**
     * Tracks whether the latest tokenization pass produced a token from quoted
     * text. Redirection parsing needs this so `echo "> literal"` remains data
     * instead of becoming a write to `/literal`.
     *
     * @var array<int, bool>
     */
    private array $lastTokenQuoteMask = [];

    private function extractRedirections(array $tokens, string $cwd): array
    {
        $clean = [];
        $cleanQuoteMask = [];
        $redirections = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (($this->lastTokenQuoteMask[$i] ?? false) === true) {
                $clean[] = $token;
                $cleanQuoteMask[] = true;

                continue;
            }
            if ($token === '2>&1') {
                $redirections[] = [
                    'stream' => 'stderr',
                    'mode' => 'merge',
                    'path' => '&1',
                ];

                continue;
            }
            if (in_array($token, ['>', '1>', '>>', '1>>', '2>', '2>>'], true)) {
                $target = $tokens[$i + 1] ?? null;
                if ($target === null || ($this->lastTokenQuoteMask[$i + 1] ?? false) === false && $this->looksLikeRedirectionToken((string) $target)) {
                    throw VfsError::invalid("Redirection {$token} requires a target path.");
                }

                $redirections[] = [
                    'stream' => str_starts_with($token, '2') ? 'stderr' : 'stdout',
                    'mode' => str_contains($token, '>>') ? 'append' : 'overwrite',
                    'path' => $this->path((string) $target, $cwd),
                ];
                $i++;

                continue;
            }
            if (preg_match('/^(2|1)>&(1|2)$/', $token, $matches) === 1) {
                $redirections[] = [
                    'stream' => $matches[1] === '2' ? 'stderr' : 'stdout',
                    'mode' => 'merge',
                    'path' => '&'.$matches[2],
                ];

                continue;
            }
            if (preg_match('/^(2|1)?(>>?)(.+)$/', $token, $matches) === 1) {
                $redirections[] = [
                    'stream' => ($matches[1] ?? '') === '2' ? 'stderr' : 'stdout',
                    'mode' => $matches[2] === '>>' ? 'append' : 'overwrite',
                    'path' => $this->path($matches[3], $cwd),
                ];

                continue;
            }
            if (preg_match('/^\d+(?:>>?|>&)/', $token) === 1) {
                throw VfsError::unsupported("Unsupported file descriptor redirection: {$token}");
            }

            $clean[] = $token;
            $cleanQuoteMask[] = false;
        }

        $this->lastTokenQuoteMask = $cleanQuoteMask;

        return [$clean, $redirections];
    }

    private function looksLikeRedirectionToken(string $token): bool
    {
        return $token === '2>&1'
            || in_array($token, ['>', '1>', '>>', '1>>', '2>', '2>>'], true)
            || preg_match('/^(2|1)>&(1|2)$/', $token) === 1
            || preg_match('/^(2|1)?(>>?)(.+)$/', $token) === 1;
    }
}
