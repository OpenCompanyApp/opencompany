<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * argv tokenization and command alias normalization for VFS shell commands.
 */
trait ParsesVfsCommandTokens
{
    private function canonicalCommandName(string $name): ?string
    {
        $aliases = [
            'dir' => 'ls',
            'll' => 'ls',
            'egrep' => 'grep',
            'fgrep' => 'grep',
            'more' => 'cat',
            'less' => 'cat',
            'rmdir' => 'rm',
        ];
        $canonical = $aliases[$name] ?? $name;

        return array_key_exists($canonical, self::commandCatalog()) ? $canonical : null;
    }

    private function tokenize(string $input): array
    {
        $tokens = [];
        $quoted = [];
        $current = '';
        $quote = null;
        $escaped = false;
        $currentWasQuoted = false;

        foreach (str_split(trim($input)) as $char) {
            if ($escaped) {
                if ($quote === '"') {
                    $current .= in_array($char, ['"', '\\', '$', '`'], true) ? $char : '\\'.$char;
                } else {
                    $current .= $char;
                }
                $escaped = false;

                continue;
            }
            if ($char === '\\') {
                if ($quote === "'") {
                    $current .= $char;

                    continue;
                }
                $escaped = true;

                continue;
            }
            if (($char === '"' || $char === "'") && $quote === null) {
                $quote = $char;
                $currentWasQuoted = true;

                continue;
            }
            if ($char === $quote) {
                $quote = null;

                continue;
            }
            if (ctype_space($char) && $quote === null) {
                if ($current !== '' || $currentWasQuoted) {
                    $tokens[] = $current;
                    $quoted[] = $currentWasQuoted;
                    $current = '';
                    $currentWasQuoted = false;
                }

                continue;
            }
            $current .= $char;
        }

        if ($escaped || $quote !== null) {
            throw VfsError::invalid('Unterminated quoted or escaped shell token.');
        }

        if ($current !== '' || $currentWasQuoted) {
            $tokens[] = $current;
            $quoted[] = $currentWasQuoted;
        }

        $this->lastTokenQuoteMask = $quoted;

        return $tokens;
    }
}
