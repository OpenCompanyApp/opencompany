<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * Guards command option coverage before commands execute.
 *
 * Parsing helpers extract supported values; this concern owns the stricter
 * preflight check that rejects unsupported flags even on command branches that
 * would otherwise be skipped by && or ||.
 */
trait ValidatesVfsCommandOptions
{
    /**
     * @param  list<string>  $allowedShort
     * @param  list<string>  $allowedValueOptions
     * @param  list<string>  $allowedLongFlags
     */
    private function assertSupportedOptions(array $tokens, string $command, array $allowedShort = [], array $allowedValueOptions = [], array $allowedLongFlags = []): void
    {
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token === '--') {
                return;
            }
            if (! str_starts_with($token, '-') || $token === '-') {
                continue;
            }
            if (in_array($token, $allowedValueOptions, true)) {
                $i++;

                continue;
            }
            if (preg_match('/^-([A-Za-z])(.+)$/', $token, $matches) === 1 && in_array('-'.$matches[1], $allowedValueOptions, true)) {
                continue;
            }
            if ($this->isInlineValueOption($token, array_map(fn (string $option): string => $option.'=', array_filter($allowedValueOptions, fn (string $option): bool => str_starts_with($option, '--'))))) {
                continue;
            }
            if (in_array($token, $allowedLongFlags, true)) {
                continue;
            }
            if (preg_match('/^-\d+[a-zA-Z]?$/', $token) === 1 && in_array('-N', $allowedShort, true)) {
                continue;
            }
            if (str_starts_with($token, '--')) {
                throw VfsError::unsupported("Unsupported {$command} flag: {$token}");
            }
            foreach (str_split(ltrim($token, '-')) as $flag) {
                if (! in_array($flag, $allowedShort, true)) {
                    throw VfsError::unsupported("Unsupported {$command} flag: -{$flag}");
                }
            }
        }
    }
}
