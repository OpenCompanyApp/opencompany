<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * Quote-aware shell command grouping and pipe splitting.
 */
trait ParsesVfsCommandGroups
{
    private function splitCommandGroups(string $command): array
    {
        $groups = [];
        $current = '';
        $quote = null;
        $escaped = false;
        $operator = null;
        $chars = str_split($command);

        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];
            $next = $chars[$i + 1] ?? null;
            if ($escaped) {
                $current .= '\\'.$char;
                $escaped = false;

                continue;
            }
            if ($char === '\\') {
                $escaped = true;

                continue;
            }
            if (($char === '"' || $char === "'") && $quote === null) {
                $quote = $char;
                $current .= $char;

                continue;
            }
            if ($char === $quote) {
                $quote = null;
                $current .= $char;

                continue;
            }
            if ($quote === null && ($char === ';' || ($char === '&' && $next === '&') || ($char === '|' && $next === '|'))) {
                if (trim($current) === '') {
                    throw VfsError::invalid('Missing command around shell operator.');
                }
                $groups[] = ['operator' => $operator, 'command' => trim($current)];
                $operator = $char === ';' ? ';' : $char.$next;
                $current = '';
                if ($char !== ';') {
                    $i++;
                }

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $groups[] = ['operator' => $operator, 'command' => trim($current)];
        } elseif ($operator !== null) {
            throw VfsError::invalid('Missing command after shell operator.');
        }
        if ($escaped || $quote !== null) {
            throw VfsError::invalid('Unterminated quoted or escaped shell token.');
        }

        return $groups;
    }

    private function splitPipes(string $command): array
    {
        return $this->splitOutsideQuotes($command, '|');
    }

    private function splitOutsideQuotes(string $input, string $delimiter): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $escaped = false;

        foreach (str_split($input) as $char) {
            if ($escaped) {
                $current .= '\\'.$char;
                $escaped = false;

                continue;
            }
            if ($char === '\\') {
                $escaped = true;

                continue;
            }
            if (($char === '"' || $char === "'") && $quote === null) {
                $quote = $char;
                $current .= $char;

                continue;
            }
            if ($char === $quote) {
                $quote = null;
                $current .= $char;

                continue;
            }
            if ($char === $delimiter && $quote === null) {
                if (trim($current) === '') {
                    throw VfsError::invalid('Missing command around pipe.');
                }
                $parts[] = trim($current);
                $current = '';

                continue;
            }
            $current .= $char;
        }

        if (trim($current) === '') {
            if ($parts !== []) {
                throw VfsError::invalid('Missing command after pipe.');
            }
        } else {
            $parts[] = trim($current);
        }
        if ($escaped || $quote !== null) {
            throw VfsError::invalid('Unterminated quoted or escaped shell token.');
        }

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }
}
