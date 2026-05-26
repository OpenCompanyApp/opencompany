<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Unix-style text filters that operate on stdin or explicitly readable VFS paths.
 */
trait ExecutesVfsTextFilters
{
    private function sort(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $flags = $this->shortFlags($tokens, ['r', 'n', 'u']);
        $key = $this->optionValue($tokens, ['-k']);
        $content = $stdin;
        if ($content === null) {
            $paths = $this->pathArgs($tokens, $cwd);
            $content = $paths === [] ? '' : implode("\n", array_map(fn (string $path): string => $this->vfs->read($agent, $path, $budget), $paths));
        }
        $lines = VfsText::lines($content);
        $keyIndex = $key !== null ? max(0, (int) preg_replace('/[^0-9].*/', '', $key) - 1) : null;

        usort($lines, function (string $a, string $b) use ($flags, $keyIndex): int {
            $left = $keyIndex === null ? $a : (preg_split('/\s+/', trim($a))[$keyIndex] ?? '');
            $right = $keyIndex === null ? $b : (preg_split('/\s+/', trim($b))[$keyIndex] ?? '');
            $comparison = ($flags['n'] ?? false) ? ((float) $left <=> (float) $right) : strcmp($left, $right);

            return ($flags['r'] ?? false) ? -$comparison : $comparison;
        });

        if ($flags['u'] ?? false) {
            $lines = array_values(array_unique($lines));
        }

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function uniq(array $tokens, ?string $stdin): array
    {
        $flags = $this->shortFlags($tokens, ['c', 'd', 'u']);
        $lines = VfsText::lines($stdin ?? '');
        $groups = [];
        foreach ($lines as $line) {
            if ($groups !== [] && $groups[count($groups) - 1]['line'] === $line) {
                $groups[count($groups) - 1]['count']++;
            } else {
                $groups[] = ['line' => $line, 'count' => 1];
            }
        }

        $output = [];
        foreach ($groups as $group) {
            if (($flags['d'] ?? false) && $group['count'] < 2) {
                continue;
            }
            if (($flags['u'] ?? false) && $group['count'] !== 1) {
                continue;
            }

            $output[] = ($flags['c'] ?? false)
                ? str_pad((string) $group['count'], 7, ' ', STR_PAD_LEFT).' '.$group['line']
                : $group['line'];
        }

        return ['stdout' => implode("\n", $output), 'class' => 'read-transform'];
    }

    private function cut(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptions($tokens, 'cut', [], ['-d', '-f']);
        $delimiter = $this->optionValueWithInline($tokens, '-d') ?? "\t";
        $field = max(1, (int) ($this->optionValueWithInline($tokens, '-f') ?? 1));
        $lines = array_map(function (string $line) use ($delimiter, $field): string {
            $parts = explode($delimiter, $line);

            return $parts[$field - 1] ?? '';
        }, VfsText::lines($stdin ?? ''));

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function tr(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptions($tokens, 'tr', ['d']);
        if (($tokens[0] ?? null) === '-d') {
            $delete = $this->expandTrSet(stripcslashes((string) ($tokens[1] ?? '')));

            return ['stdout' => str_replace(str_split($delete), '', $stdin ?? ''), 'class' => 'read-transform'];
        }

        if (count($tokens) < 2) {
            throw VfsError::invalid('tr requires source and target character sets.');
        }

        $source = $this->expandTrSet(stripcslashes($tokens[0]));
        $target = $this->expandTrSet(stripcslashes($tokens[1]));
        $map = [];
        foreach (str_split($source) as $index => $char) {
            $map[$char] = $target[$index] ?? substr($target, -1);
        }

        return ['stdout' => strtr($stdin ?? '', $map), 'class' => 'read-transform'];
    }

    private function expandTrSet(string $value): string
    {
        return preg_replace_callback('/(.)-(.)/', function (array $matches): string {
            $start = ord($matches[1]);
            $end = ord($matches[2]);
            if ($start > $end) {
                return $matches[0];
            }

            return implode('', array_map('chr', range($start, $end)));
        }, $value) ?? $value;
    }
}
