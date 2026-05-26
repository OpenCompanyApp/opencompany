<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Field-oriented unix transforms that need either a command callback or VFS reads.
 */
trait ExecutesVfsFieldTransforms
{
    private function awk(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptions($tokens, 'awk', [], ['-F']);
        $delimiter = null;
        $program = null;
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i] === '-F') {
                $delimiter = (string) ($tokens[$i + 1] ?? '');
                $i++;

                continue;
            }
            if (str_starts_with($tokens[$i], '-F') && strlen($tokens[$i]) > 2) {
                $delimiter = substr($tokens[$i], 2);

                continue;
            }
            $program ??= $tokens[$i];
        }

        if ($program === null || preg_match('/^\{\s*print\s+(.+)\s*\}$/', $program, $matches) !== 1) {
            throw VfsError::unsupported('Only awk field extraction is supported, e.g. awk -F , \'{print $2}\'.');
        }

        $selectors = array_map('trim', explode(',', $matches[1]));
        $lines = array_map(function (string $line) use ($delimiter, $selectors): string {
            $fields = $delimiter === null || $delimiter === ''
                ? preg_split('/\s+/', trim($line)) ?: []
                : explode($delimiter, $line);

            return implode(' ', array_map(function (string $selector) use ($fields): string {
                if ($selector === '$0') {
                    return implode(' ', $fields);
                }
                if (preg_match('/^\$(\d+)$/', $selector, $fieldMatch) !== 1) {
                    throw VfsError::unsupported('Only awk print field selectors like $1 and $2 are supported.');
                }

                return $fields[((int) $fieldMatch[1]) - 1] ?? '';
            }, $selectors));
        }, VfsText::lines($stdin ?? ''));

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function xargs(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $items = array_values(array_filter(array_map('trim', VfsText::lines($stdin ?? '')), fn (string $line): bool => $line !== ''));
        $items = array_slice($items, 0, $budget->maxEntries);
        $placeholder = null;
        $maxArgs = 1;
        $commandStart = 0;
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token === '-I') {
                $placeholder = (string) ($tokens[$i + 1] ?? '{}');
                $i++;

                continue;
            }
            if (str_starts_with($token, '-I') && strlen($token) > 2) {
                $placeholder = substr($token, 2);

                continue;
            }
            if (in_array($token, ['-n', '--max-args'], true)) {
                if (! isset($tokens[$i + 1]) || ! preg_match('/^\d+$/', (string) $tokens[$i + 1])) {
                    throw VfsError::invalid('xargs max args requires a positive integer.');
                }
                $maxArgs = max(1, (int) ($tokens[$i + 1] ?? 1));
                $i++;

                continue;
            }
            if (str_starts_with($token, '--max-args=')) {
                $value = substr($token, strlen('--max-args='));
                if (! preg_match('/^\d+$/', $value)) {
                    throw VfsError::invalid('xargs max args requires a positive integer.');
                }
                $maxArgs = max(1, (int) $value);

                continue;
            }
            if (str_starts_with($token, '-')) {
                throw VfsError::unsupported("Unsupported xargs flag: {$token}");
            }

            $commandStart = $i;
            break;
        }
        $tokens = array_slice($tokens, $commandStart);
        $baseTokens = $tokens === [] ? ['echo'] : $tokens;
        $outputs = [];

        foreach (array_chunk($items, $placeholder === null ? $maxArgs : 1) as $chunk) {
            $item = implode(' ', $chunk);
            if ($placeholder !== null) {
                $replaced = false;
                $commandTokens = array_map(function (string $token) use ($placeholder, $item, &$replaced): string {
                    if (str_contains($token, $placeholder)) {
                        $replaced = true;

                        return str_replace($placeholder, $item, $token);
                    }

                    return $token;
                }, $baseTokens);
                if (! $replaced) {
                    $commandTokens[] = $item;
                }
            } else {
                $commandTokens = [...$baseTokens, ...$chunk];
            }
            $result = $this->runCommand($agent, $commandTokens, $cwd, $budget, null);
            $stdout = trim((string) ($result['stdout'] ?? ''));
            if ($stdout !== '') {
                $outputs[] = $stdout;
            }
        }

        return [
            'stdout' => implode("\n", $outputs),
            'cwd' => $cwd,
            'class' => 'read-transform',
            'returned' => count($items),
            'limit' => $budget->maxEntries,
            'truncated' => count(VfsText::lines($stdin ?? '')) > count($items),
        ];
    }

    private function paste(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptions($tokens, 'paste');
        $paths = $this->pathArgs($tokens, $cwd);
        if ($paths === []) {
            return ['stdout' => implode("\t", VfsText::lines($stdin ?? '')), 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        $columns = array_map(fn (string $path): array => VfsText::lines($this->vfs->read($agent, $path, $budget)), $paths);
        $max = max(array_map('count', $columns));
        $lines = [];
        for ($row = 0; $row < $max; $row++) {
            $lines[] = implode("\t", array_map(fn (array $column): string => $column[$row] ?? '', $columns));
        }

        return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'read-transform'];
    }
}
