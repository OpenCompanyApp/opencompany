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
        $this->assertSupportedOptionsForCommand($tokens, 'sort');
        $flags = $this->shortFlags($tokens, ['r', 'n', 'u', 'f', 'h', 'V', 's', 'M']);
        $key = $this->optionValue($tokens, ['-k']);
        $delimiter = $this->optionValue($tokens, ['-t']);
        $content = $stdin;
        if ($content === null) {
            $paths = $this->pathArgsWithValueOptions($tokens, $cwd, ['-k', '-t']);
            $content = $paths === [] ? '' : implode("\n", array_map(fn (string $path): string => $this->vfs->read($agent, $path, $budget), $paths));
        }
        $lines = VfsText::lines($content);
        $keyIndex = $key !== null ? max(0, (int) preg_replace('/[^0-9].*/', '', $key) - 1) : null;

        usort($lines, function (string $a, string $b) use ($flags, $keyIndex, $delimiter): int {
            $split = fn (string $line): array => $delimiter === null ? (preg_split('/\s+/', trim($line)) ?: []) : explode($delimiter, $line);
            $left = $keyIndex === null ? $a : ($split($a)[$keyIndex] ?? '');
            $right = $keyIndex === null ? $b : ($split($b)[$keyIndex] ?? '');
            if ($flags['f'] ?? false) {
                $left = strtolower($left);
                $right = strtolower($right);
            }
            $comparison = match (true) {
                $flags['n'] ?? false => (float) $left <=> (float) $right,
                $flags['h'] ?? false => $this->humanSortValue($left) <=> $this->humanSortValue($right),
                $flags['V'] ?? false => version_compare($left, $right),
                $flags['M'] ?? false => $this->monthSortValue($left) <=> $this->monthSortValue($right),
                default => strcmp($left, $right),
            };

            return ($flags['r'] ?? false) ? -$comparison : $comparison;
        });

        if ($flags['u'] ?? false) {
            $lines = array_values(array_unique($lines));
        }

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function uniq(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'uniq');
        $flags = $this->shortFlags($tokens, ['c', 'd', 'u', 'i']);
        $skipFields = (int) ($this->optionValue($tokens, ['-f']) ?? 0);
        $skipChars = (int) ($this->optionValue($tokens, ['-s']) ?? 0);
        $width = $this->optionValue($tokens, ['-w']);
        $lines = VfsText::lines($stdin ?? '');
        $groups = [];
        foreach ($lines as $line) {
            $key = $this->uniqKey($line, $skipFields, $skipChars, $width === null ? null : (int) $width, $flags['i'] ?? false);
            if ($groups !== [] && $groups[count($groups) - 1]['key'] === $key) {
                $groups[count($groups) - 1]['count']++;
            } else {
                $groups[] = ['line' => $line, 'key' => $key, 'count' => 1];
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
        $this->assertSupportedOptionsForCommand($tokens, 'cut');
        $delimiter = $this->optionValueWithInline($tokens, '-d') ?? "\t";
        $complement = in_array('--complement', $tokens, true);
        if ($this->optionWasProvided($tokens, ['-c'])) {
            $ranges = $this->parseRanges((string) ($this->optionValueWithInline($tokens, '-c') ?? '1'));
            $lines = array_map(fn (string $line): string => $this->selectCharacters($line, $ranges, $complement), VfsText::lines($stdin ?? ''));

            return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
        }

        $ranges = $this->parseRanges((string) ($this->optionValueWithInline($tokens, '-f') ?? '1'));
        $lines = array_map(function (string $line) use ($delimiter, $ranges, $complement): string {
            $parts = explode($delimiter, $line);
            $selected = [];
            foreach ($parts as $index => $part) {
                $include = $this->rangeContains($ranges, $index + 1);
                if ($complement) {
                    $include = ! $include;
                }
                if ($include) {
                    $selected[] = $part;
                }
            }

            return implode($delimiter, $selected);
        }, VfsText::lines($stdin ?? ''));

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function tr(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'tr');
        $flags = $this->shortFlags($tokens, ['d', 's', 'c']);
        $args = array_values(array_filter($tokens, fn (string $token): bool => ! str_starts_with($token, '-')));
        if ($flags['d'] ?? false) {
            $delete = $this->expandTrSet(stripcslashes((string) ($args[0] ?? '')));
            if ($flags['c'] ?? false) {
                $keep = array_flip(str_split($delete));

                return ['stdout' => implode('', array_filter(str_split($stdin ?? ''), fn (string $char): bool => isset($keep[$char]))), 'class' => 'read-transform'];
            }

            return ['stdout' => str_replace(str_split($delete), '', $stdin ?? ''), 'class' => 'read-transform'];
        }

        if (($flags['s'] ?? false) && count($args) === 1) {
            $target = $this->expandTrSet(stripcslashes($args[0]));
            $squeezed = preg_replace('/(['.preg_quote($target, '/').'])\1+/', '$1', $stdin ?? '') ?? '';

            return ['stdout' => $squeezed, 'class' => 'read-transform'];
        }

        if (count($args) < 2) {
            throw VfsError::invalid('tr requires source and target character sets.');
        }

        $source = $this->expandTrSet(stripcslashes($args[0]));
        $target = $this->expandTrSet(stripcslashes($args[1]));
        if ($flags['s'] ?? false) {
            $squeezed = preg_replace('/(['.preg_quote($target, '/').'])\1+/', '$1', $stdin ?? '') ?? '';
            if ($source === $target) {
                return ['stdout' => $squeezed, 'class' => 'read-transform'];
            }
            $stdin = $squeezed;
        }
        $map = [];
        foreach (str_split($source) as $index => $char) {
            $map[$char] = $target[$index] ?? substr($target, -1);
        }

        return ['stdout' => strtr($stdin ?? '', $map), 'class' => 'read-transform'];
    }

    private function tac(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $content = $stdin;
        if ($content === null) {
            $paths = $this->pathArgs($tokens, $cwd);
            $content = $paths === [] ? '' : implode("\n", array_map(fn (string $path): string => $this->vfs->read($agent, $path, $budget), $paths));
        }

        return ['stdout' => implode("\n", array_reverse(VfsText::lines($content))), 'class' => 'read-transform'];
    }

    private function seq(array $tokens): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'seq');
        $separator = $this->optionValue($tokens, ['-s']) ?? "\n";
        $format = $this->optionValue($tokens, ['-f']);
        $width = $this->shortFlags($tokens, ['w'])['w'] ?? false;
        $numbers = array_values(array_filter($tokens, fn (string $token): bool => ! str_starts_with($token, '-')));
        $start = count($numbers) >= 2 ? (float) $numbers[0] : 1.0;
        $step = count($numbers) >= 3 ? (float) $numbers[1] : 1.0;
        $end = (float) ($numbers[count($numbers) - 1] ?? 1);
        if ($step == 0.0) {
            throw VfsError::invalid('seq step must not be zero.');
        }
        $values = [];
        for ($value = $start; $step > 0 ? $value <= $end : $value >= $end; $value += $step) {
            if (count($values) >= 10_000) {
                throw VfsError::invalid('seq output exceeds VFS limit.');
            }
            $rendered = ((float) (int) $value) === $value ? (string) (int) $value : (string) $value;
            if ($format !== null) {
                $rendered = sprintf($format, $value);
            } elseif ($width) {
                $rendered = str_pad($rendered, strlen((string) (int) $end), '0', STR_PAD_LEFT);
            }
            $values[] = $rendered;
        }

        return ['stdout' => implode($separator, $values), 'class' => 'read-transform'];
    }

    private function base64(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'base64');
        $content = $stdin;
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, ['-t', '-1', '-2', '-a', '-v', '-e', '-o']);
        if ($content === null && $paths !== []) {
            $content = implode("\n", array_map(fn (string $path): string => $this->vfs->read($agent, $path, $budget), $paths));
        }
        $content ??= '';
        $decode = ($this->shortFlags($tokens, ['d', 'D'])['d'] ?? false) || ($this->shortFlags($tokens, ['d', 'D'])['D'] ?? false);
        if ($decode) {
            $decoded = base64_decode(preg_replace('/\s+/', '', $content) ?? $content, true);
            if ($decoded === false) {
                throw VfsError::invalid('base64 input is not valid.');
            }

            return ['stdout' => $decoded, 'class' => 'read-transform'];
        }
        $encoded = base64_encode($content);
        $wrap = $this->optionValue($tokens, ['-w']);
        if ($wrap !== null && (int) $wrap > 0) {
            $encoded = chunk_split($encoded, (int) $wrap, "\n");
            $encoded = rtrim($encoded, "\n");
        }

        return ['stdout' => $encoded, 'class' => 'read-transform'];
    }

    private function column(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'column');
        $separator = $this->optionValue($tokens, ['-s']) ?? null;
        $outputSeparator = $this->optionValue($tokens, ['-o']) ?? '  ';
        $rows = array_map(fn (string $line): array => $separator === null ? (preg_split('/\s+/', trim($line)) ?: []) : explode($separator, $line), VfsText::lines($stdin ?? ''));
        $widths = [];
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, strlen($cell));
            }
        }
        $lines = array_map(function (array $row) use ($widths, $outputSeparator): string {
            return rtrim(implode($outputSeparator, array_map(fn (string $cell, int $index): string => str_pad($cell, $widths[$index] ?? strlen($cell)), $row, array_keys($row))));
        }, $rows);

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function fold(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'fold');
        $width = max(1, (int) ($this->optionValue($tokens, ['-w']) ?? 80));
        $breakWords = ! ($this->shortFlags($tokens, ['s'])['s'] ?? false);
        $lines = [];
        foreach (VfsText::lines($stdin ?? '') as $line) {
            $lines[] = wordwrap($line, $width, "\n", $breakWords);
        }

        return ['stdout' => implode("\n", $lines), 'class' => 'read-transform'];
    }

    private function fmt(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'fmt');
        $width = max(1, (int) ($this->optionValue($tokens, ['-w']) ?? 75));
        $paragraphs = preg_split('/\R{2,}/', trim($stdin ?? '')) ?: [];
        $formatted = array_map(fn (string $paragraph): string => wordwrap(preg_replace('/\s+/', ' ', trim($paragraph)) ?? trim($paragraph), $width), $paragraphs);

        return ['stdout' => implode("\n\n", $formatted), 'class' => 'read-transform'];
    }

    private function expand(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'expand');
        $tab = max(1, (int) ($this->optionValue($tokens, ['-t']) ?? 8));

        return ['stdout' => str_replace("\t", str_repeat(' ', $tab), $stdin ?? ''), 'class' => 'read-transform'];
    }

    private function unexpand(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'unexpand');
        $tab = max(1, (int) ($this->optionValue($tokens, ['-t']) ?? 8));

        return ['stdout' => preg_replace('/ {'.$tab.'}/', "\t", $stdin ?? '') ?? '', 'class' => 'read-transform'];
    }

    private function tsort(?string $stdin): array
    {
        $edges = [];
        $nodes = [];
        foreach (VfsText::lines($stdin ?? '') as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) < 2) {
                continue;
            }
            [$from, $to] = [$parts[0], $parts[1]];
            $nodes[$from] = true;
            $nodes[$to] = true;
            $edges[$from][] = $to;
        }
        $visited = [];
        $temp = [];
        $out = [];
        $visit = function (string $node) use (&$visit, &$visited, &$temp, &$out, $edges): void {
            if (isset($visited[$node])) {
                return;
            }
            if (isset($temp[$node])) {
                throw VfsError::invalid('tsort input contains a cycle.');
            }
            $temp[$node] = true;
            foreach ($edges[$node] ?? [] as $next) {
                $visit($next);
            }
            unset($temp[$node]);
            $visited[$node] = true;
            $out[] = $node;
        };
        foreach (array_keys($nodes) as $node) {
            $visit($node);
        }

        return ['stdout' => implode("\n", array_reverse($out)), 'class' => 'read-transform'];
    }

    private function look(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'look');
        $ignoreCase = $this->shortFlags($tokens, ['f'])['f'] ?? false;
        $args = array_values(array_filter($tokens, fn (string $token): bool => ! str_starts_with($token, '-')));
        $prefix = $args[0] ?? throw VfsError::invalid('look requires a prefix.');
        $content = $stdin;
        if ($content === null && isset($args[1])) {
            $content = $this->vfs->read($agent, $this->path($args[1], $cwd), $budget);
        }
        $matches = array_filter(VfsText::lines($content ?? ''), fn (string $line): bool => $ignoreCase ? str_starts_with(strtolower($line), strtolower($prefix)) : str_starts_with($line, $prefix));

        return ['stdout' => implode("\n", $matches), 'class' => 'read-transform'];
    }

    private function join(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'join');
        $delimiter = $this->optionValue($tokens, ['-t']) ?? ' ';
        $leftField = max(1, (int) ($this->optionValue($tokens, ['-1']) ?? 1)) - 1;
        $rightField = max(1, (int) ($this->optionValue($tokens, ['-2']) ?? 1)) - 1;
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('join requires two paths.');
        }
        $leftRows = array_map(fn (string $line): array => explode($delimiter, $line), VfsText::lines($this->vfs->read($agent, $paths[0], $budget)));
        $rightRows = array_map(fn (string $line): array => explode($delimiter, $line), VfsText::lines($this->vfs->read($agent, $paths[1], $budget)));
        $rightByKey = [];
        foreach ($rightRows as $row) {
            $rightByKey[$row[$rightField] ?? ''][] = $row;
        }
        $out = [];
        foreach ($leftRows as $left) {
            $key = $left[$leftField] ?? '';
            foreach ($rightByKey[$key] ?? [] as $right) {
                $out[] = implode($delimiter, array_merge([$key], array_values(array_diff_key($left, [$leftField => true])), array_values(array_diff_key($right, [$rightField => true]))));
            }
        }

        return ['stdout' => implode("\n", $out), 'class' => 'read-transform'];
    }

    private function shuf(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'shuf');
        $items = $this->shortFlags($tokens, ['e', 'z', 'r'])['e'] ?? false
            ? array_values(array_filter($tokens, fn (string $token): bool => ! str_starts_with($token, '-')))
            : VfsText::lines($stdin ?? '');
        if ($this->shortFlags($tokens, ['e', 'z', 'r'])['r'] ?? false) {
            $items = $items === [] ? [] : array_map(fn (): string => $items[array_rand($items)], range(1, max(1, (int) ($this->optionValue($tokens, ['-n']) ?? count($items)))));
        } else {
            shuffle($items);
        }
        $count = $this->optionValue($tokens, ['-n']);
        if ($count !== null) {
            $items = array_slice($items, 0, max(0, (int) $count));
        }

        return ['stdout' => implode(($this->shortFlags($tokens, ['e', 'z', 'r'])['z'] ?? false) ? "\0" : "\n", $items), 'class' => 'read-transform'];
    }

    private function bc(array $tokens, ?string $stdin): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'bc');
        $expr = trim($stdin ?? implode(' ', array_filter($tokens, fn (string $token): bool => ! str_starts_with($token, '-'))));

        return ['stdout' => $this->evaluateArithmeticExpression($expr), 'class' => 'read-transform'];
    }

    private function expr(array $tokens): array
    {
        $expr = implode(' ', $tokens);

        return ['stdout' => $this->evaluateArithmeticExpression($expr), 'class' => 'read-transform'];
    }

    private function evaluateArithmeticExpression(string $expr): string
    {
        if (! preg_match('/^[0-9+\-*\/().%\s]+$/', $expr)) {
            throw VfsError::unsupported('Only simple numeric expressions are supported.');
        }

        $tokens = preg_split('/\s*([()+\-*\/%])\s*|\s+/', trim($expr), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $index = 0;
        $parseExpression = function () use (&$parseExpression, &$parseTerm, &$tokens, &$index): float {
            $value = $parseTerm();
            while (($tokens[$index] ?? null) === '+' || ($tokens[$index] ?? null) === '-') {
                $operator = $tokens[$index++];
                $right = $parseTerm();
                $value = $operator === '+' ? $value + $right : $value - $right;
            }

            return $value;
        };
        $parseTerm = function () use (&$parseTerm, &$parseFactor, &$tokens, &$index): float {
            $value = $parseFactor();
            while (in_array($tokens[$index] ?? null, ['*', '/', '%'], true)) {
                $operator = $tokens[$index++];
                $right = $parseFactor();
                if ($right == 0.0 && in_array($operator, ['/', '%'], true)) {
                    throw VfsError::invalid('Division by zero.');
                }
                $value = match ($operator) {
                    '*' => $value * $right,
                    '/' => $value / $right,
                    default => fmod($value, $right),
                };
            }

            return $value;
        };
        $parseFactor = function () use (&$parseExpression, &$tokens, &$index): float {
            $token = $tokens[$index] ?? null;
            if ($token === '+' || $token === '-') {
                $index++;

                return ($token === '-' ? -1 : 1) * $this->parseArithmeticNumberOrGroup($parseExpression, $tokens, $index);
            }

            return $this->parseArithmeticNumberOrGroup($parseExpression, $tokens, $index);
        };

        $value = $parseExpression();
        if ($index !== count($tokens)) {
            throw VfsError::invalid('Invalid numeric expression.');
        }

        return floor($value) == $value ? (string) (int) $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    /**
     * @param  callable(): float  $parseExpression
     * @param  list<string>  $tokens
     */
    private function parseArithmeticNumberOrGroup(callable $parseExpression, array $tokens, int &$index): float
    {
        $token = $tokens[$index] ?? null;
        if ($token === '(') {
            $index++;
            $value = $parseExpression();
            if (($tokens[$index] ?? null) !== ')') {
                throw VfsError::invalid('Unclosed numeric expression group.');
            }
            $index++;

            return $value;
        }
        if ($token === null || ! preg_match('/^\d+(?:\.\d+)?$/', $token)) {
            throw VfsError::invalid('Invalid numeric expression.');
        }
        $index++;

        return (float) $token;
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

    private function humanSortValue(string $value): float
    {
        if (preg_match('/^\s*([0-9.]+)\s*([KMGTP]?)/i', $value, $matches) !== 1) {
            return (float) $value;
        }
        $factor = match (strtoupper($matches[2] ?? '')) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            'T' => 1024 ** 4,
            'P' => 1024 ** 5,
            default => 1,
        };

        return (float) $matches[1] * $factor;
    }

    private function monthSortValue(string $value): int
    {
        $months = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];

        return $months[strtoupper(substr(trim($value), 0, 3))] ?? 0;
    }

    private function uniqKey(string $line, int $skipFields, int $skipChars, ?int $width, bool $ignoreCase): string
    {
        $parts = preg_split('/\s+/', $line) ?: [];
        $key = implode(' ', array_slice($parts, $skipFields));
        $key = substr($key, $skipChars);
        if ($width !== null) {
            $key = substr($key, 0, $width);
        }

        return $ignoreCase ? strtolower($key) : $key;
    }

    /**
     * @return list<array{0:int,1:int|null}>
     */
    private function parseRanges(string $value): array
    {
        return array_map(function (string $part): array {
            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part, 2);

                return [max(1, (int) ($start === '' ? 1 : $start)), $end === '' ? null : max(1, (int) $end)];
            }

            return [max(1, (int) $part), max(1, (int) $part)];
        }, array_filter(explode(',', $value)));
    }

    private function rangeContains(array $ranges, int $number): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($number >= $start && ($end === null || $number <= $end)) {
                return true;
            }
        }

        return false;
    }

    private function selectCharacters(string $line, array $ranges, bool $complement): string
    {
        $out = '';
        foreach (str_split($line) as $index => $char) {
            $include = $this->rangeContains($ranges, $index + 1);
            if ($complement) {
                $include = ! $include;
            }
            if ($include) {
                $out .= $char;
            }
        }

        return $out;
    }
}
