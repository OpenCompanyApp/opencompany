<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsArgs;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Unix text filters over stdin or file operands.
 *
 * Invariant: every filter reads its file operands when given (`-` = stdin)
 * and stdin otherwise. A filter that silently ignored `cut -d, -f1 data.csv`
 * and printed nothing with exit 0 was the most misleading failure the shell
 * had; none may regress to stdin-only.
 *
 * Output is POSIX line output (every line newline-terminated).
 */
trait ExecutesVfsTextFilters
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function sort(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('sort', $words);
        $lines = $this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin));
        $separator = $args->value('-t');
        if ($separator !== null) {
            $separator = $separator === '\t' ? "\t" : $separator;
            if (strlen($separator) !== 1) {
                throw VfsError::invalid('sort -t requires a single-character separator.');
            }
        }
        $global = ['n' => $args->has('n'), 'h' => $args->has('h'), 'V' => $args->has('V'), 'M' => $args->has('M'), 'f' => $args->has('f'), 'b' => $args->has('b'), 'r' => $args->has('r')];
        $keys = array_map(fn (string $spec): array => $this->parseSortKey($spec, $global), $args->values('-k'));

        $keyCompare = function (string $a, string $b) use ($keys, $global, $separator): int {
            if ($keys === []) {
                return $this->compareSortValues($a, $b, $global);
            }
            foreach ($keys as $key) {
                $cmp = $this->compareSortValues($this->sortKeyValue($a, $key, $separator), $this->sortKeyValue($b, $key, $separator), $key['mods']);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        };
        $indexed = [];
        foreach ($lines as $i => $line) {
            $indexed[] = [$line, $i];
        }
        $lastResort = ! $args->has('s') && ! $args->has('u');
        usort($indexed, function (array $a, array $b) use ($keyCompare, $lastResort, $global): int {
            $cmp = $keyCompare($a[0], $b[0]);
            if ($cmp === 0 && $lastResort) {
                // GNU last-resort comparison on the whole line.
                $cmp = (strcmp($a[0], $b[0]) <=> 0) * ($global['r'] ? -1 : 1);
            }

            return $cmp !== 0 ? $cmp : $a[1] <=> $b[1];
        });

        $sorted = array_column($indexed, 0);
        if ($args->has('u')) {
            // -u keeps the first line of each run of equal KEYS (not lines).
            $unique = [];
            foreach ($sorted as $line) {
                if ($unique !== [] && $keyCompare($unique[count($unique) - 1], $line) === 0) {
                    continue;
                }
                $unique[] = $line;
            }
            $sorted = $unique;
        }

        return ['stdout' => $this->linesOut($sorted), 'class' => 'read-transform'];
    }

    /**
     * Parse -k POS1[,POS2] where POS = F[.C][bdfhMnrV].
     *
     * @param  array<string, bool>  $global
     * @return array{start_field: int, start_char: int, end_field: int|null, end_char: int|null, mods: array<string, bool>}
     */
    private function parseSortKey(string $spec, array $global): array
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?([bdfhMnrV]*)(?:,(\d+)(?:\.(\d+))?([bdfhMnrV]*))?$/', $spec, $m) !== 1) {
            throw VfsError::invalid("sort: invalid key \"{$spec}\".", ['hint' => 'Use -k F[,F] with optional modifiers, e.g. -k 2,2n or -k3r.']);
        }
        $letters = $m[3].($m[6] ?? '');
        $mods = $letters === '' ? $global : array_merge(array_fill_keys(array_keys($global), false), array_fill_keys(str_split($letters), true));

        return [
            'start_field' => max(1, (int) $m[1]),
            'start_char' => $m[2] !== '' ? max(1, (int) $m[2]) : 1,
            'end_field' => isset($m[4]) && $m[4] !== '' ? (int) $m[4] : null,
            'end_char' => isset($m[5]) && $m[5] !== '' ? (int) $m[5] : null,
            'mods' => $mods,
        ];
    }

    /**
     * @param  array{start_field: int, start_char: int, end_field: int|null, end_char: int|null, mods: array<string, bool>}  $key
     */
    private function sortKeyValue(string $line, array $key, ?string $separator): string
    {
        if ($separator === null) {
            // Default fields: a field includes its leading blanks (GNU).
            preg_match_all('/\s*\S+/', $line, $m);
            $fields = $m[0];
        } else {
            $fields = explode($separator, $line);
        }
        $end = $key['end_field'] ?? count($fields);
        $selected = array_slice($fields, $key['start_field'] - 1, max(0, $end - $key['start_field'] + 1));
        $value = implode($separator ?? '', $selected);
        if ($key['start_char'] > 1) {
            $value = substr($value, $key['start_char'] - 1);
        }
        if (($key['mods']['b'] ?? false) || $separator === null) {
            $value = ltrim($value);
        }

        return $value;
    }

    /**
     * @param  array<string, bool>  $mods
     */
    private function compareSortValues(string $a, string $b, array $mods): int
    {
        if ($mods['b'] ?? false) {
            $a = ltrim($a);
            $b = ltrim($b);
        }
        if ($mods['f'] ?? false) {
            $a = strtolower($a);
            $b = strtolower($b);
        }
        $cmp = match (true) {
            $mods['n'] ?? false => $this->leadingNumber($a) <=> $this->leadingNumber($b),
            $mods['h'] ?? false => $this->humanSortValue($a) <=> $this->humanSortValue($b),
            $mods['V'] ?? false => version_compare($a, $b),
            $mods['M'] ?? false => $this->monthSortValue($a) <=> $this->monthSortValue($b),
            default => strcmp($a, $b) <=> 0,
        };

        return ($mods['r'] ?? false) ? -$cmp : $cmp;
    }

    private function leadingNumber(string $value): float
    {
        return preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+))/', $value, $m) === 1 ? (float) $m[1] : 0.0;
    }

    private function humanSortValue(string $value): float
    {
        if (preg_match('/^\s*([0-9.]+)\s*([KMGTP]?)/i', $value, $matches) !== 1) {
            return (float) $value;
        }

        return (float) $matches[1] * match (strtoupper($matches[2])) {
            'K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3, 'T' => 1024 ** 4, 'P' => 1024 ** 5, default => 1,
        };
    }

    private function monthSortValue(string $value): int
    {
        $months = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];

        return $months[strtoupper(substr(trim($value), 0, 3))] ?? 0;
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function uniq(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('uniq', $words);
        if (count($args->operands) > 1) {
            throw VfsError::unsupported('uniq OUTPUT operands are not supported; redirect stdout instead (uniq in > /files/out).');
        }
        $skipFields = $args->int('-f') ?? 0;
        $skipChars = $args->int('-s') ?? 0;
        $width = $args->int('-w');
        $groups = [];
        foreach ($this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin)) as $line) {
            $key = $this->uniqKey($line, $skipFields, $skipChars, $width, $args->has('i'));
            if ($groups !== [] && $groups[count($groups) - 1]['key'] === $key) {
                $groups[count($groups) - 1]['count']++;
            } else {
                $groups[] = ['line' => $line, 'key' => $key, 'count' => 1];
            }
        }

        $output = [];
        foreach ($groups as $group) {
            if (($args->has('d') && $group['count'] < 2) || ($args->has('u') && $group['count'] !== 1)) {
                continue;
            }
            $output[] = $args->has('c') ? str_pad((string) $group['count'], 7, ' ', STR_PAD_LEFT).' '.$group['line'] : $group['line'];
        }

        return ['stdout' => $this->linesOut($output), 'class' => 'read-transform'];
    }

    private function uniqKey(string $line, int $skipFields, int $skipChars, ?int $width, bool $ignoreCase): string
    {
        $key = $line;
        if ($skipFields > 0) {
            $key = preg_replace('/^(?:\s*\S+){'.$skipFields.'}/', '', $key) ?? $key;
        }
        $key = substr($key, $skipChars);
        if ($width !== null) {
            $key = substr($key, 0, $width);
        }

        return $ignoreCase ? strtolower($key) : $key;
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function cut(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('cut', $words);
        $delimiter = $args->value('-d') ?? "\t";
        if ($delimiter === '\t') {
            $delimiter = "\t";
        }
        if (strlen($delimiter) !== 1) {
            throw VfsError::invalid('cut: the delimiter must be a single character.', ['hint' => "Use awk -F '...' for multi-character separators."]);
        }
        $complement = $args->has('--complement');
        $outputDelimiter = $args->value('--output-delimiter') ?? $delimiter;
        $lines = $this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin));

        if ($args->provided(['-c', '-b'])) {
            $ranges = $this->parseRanges((string) $args->value(['-c', '-b']));
            $out = array_map(function (string $line) use ($ranges, $complement): string {
                $kept = '';
                foreach (str_split($line) as $i => $char) {
                    if ($this->rangeContains($ranges, $i + 1) !== $complement) {
                        $kept .= $char;
                    }
                }

                return $kept;
            }, $lines);

            return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
        }

        if (! $args->provided('-f')) {
            throw VfsError::invalid('cut: you must specify a list of bytes, characters, or fields (-b, -c, or -f).');
        }
        $ranges = $this->parseRanges((string) $args->value('-f'));
        $out = [];
        foreach ($lines as $line) {
            if (! str_contains($line, $delimiter)) {
                if (! $args->has('s')) {
                    $out[] = $line;
                }

                continue;
            }
            $selected = [];
            foreach (explode($delimiter, $line) as $i => $part) {
                if ($this->rangeContains($ranges, $i + 1) !== $complement) {
                    $selected[] = $part;
                }
            }
            $out[] = implode($outputDelimiter, $selected);
        }

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * @return list<array{0: int, 1: int|null}>
     */
    private function parseRanges(string $value): array
    {
        $ranges = [];
        foreach (explode(',', $value) as $part) {
            if (preg_match('/^(\d*)(-?)(\d*)$/', $part, $m) !== 1 || ($m[1] === '' && $m[3] === '')) {
                throw VfsError::invalid("cut: invalid field or character list \"{$value}\".", ['hint' => 'Examples: -f1, -f1,3, -f2-4, -f3-, -c1-10']);
            }
            $start = $m[1] === '' ? 1 : max(1, (int) $m[1]);
            $end = $m[2] === '' ? $start : ($m[3] === '' ? null : (int) $m[3]);
            $ranges[] = [$start, $end];
        }

        return $ranges;
    }

    /**
     * @param  list<array{0: int, 1: int|null}>  $ranges
     */
    private function rangeContains(array $ranges, int $number): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($number >= $start && ($end === null || $number <= $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * tr SET1 [SET2] [FILE...]: sets first, then optional file operands (VFS
     * extension; GNU tr reads only stdin).
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function tr(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('tr', $words);
        $delete = $args->has('d');
        $squeeze = $args->has('s');
        $complement = $args->has('c', 'C');
        $setCount = ($delete && ! $squeeze) || ($squeeze && ! $delete && count($args->operands) === 1) ? 1 : 2;
        if ($delete && $squeeze) {
            $setCount = 2;
        }
        if (count($args->operands) < $setCount) {
            throw VfsError::invalid('tr requires '.($setCount === 1 ? 'a character set' : 'source and target character sets').'.');
        }
        $set1 = $this->expandTrSet($args->operands[0]);
        $set2 = $setCount === 2 ? $this->expandTrSet($args->operands[1]) : '';
        $input = $this->inputFrom($agent, $args, $cwd, $stdin, $setCount);

        if ($complement) {
            $all = '';
            for ($i = 0; $i < 256; $i++) {
                $char = chr($i);
                if (! str_contains($set1, $char)) {
                    $all .= $char;
                }
            }
            $set1 = $all;
        }
        if ($delete) {
            $input = str_replace(str_split($set1 === '' ? "\0" : $set1), '', $input);
            if ($squeeze && $set2 !== '') {
                $input = $this->squeeze($input, $set2);
            }

            return ['stdout' => $input, 'class' => 'read-transform'];
        }
        if ($setCount === 2) {
            $map = [];
            $chars = str_split($set1);
            foreach ($chars as $i => $char) {
                $map[$char] = $set2[$i] ?? substr($set2, -1);
            }
            $input = strtr($input, $map);
            if ($squeeze) {
                $input = $this->squeeze($input, $set2);
            }

            return ['stdout' => $input, 'class' => 'read-transform'];
        }

        return ['stdout' => $this->squeeze($input, $set1), 'class' => 'read-transform'];
    }

    private function squeeze(string $input, string $set): string
    {
        if ($set === '') {
            return $input;
        }

        return preg_replace('/(['.preg_quote($set, '/').'])\1+/', '$1', $input) ?? $input;
    }

    /**
     * Expand ranges (a-z), escapes (\n \t \\), and POSIX classes ([:alpha:]).
     */
    private function expandTrSet(string $value): string
    {
        $classes = [
            '[:alpha:]' => implode('', array_merge(range('a', 'z'), range('A', 'Z'))),
            '[:digit:]' => '0123456789',
            '[:alnum:]' => implode('', array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'))),
            '[:lower:]' => implode('', range('a', 'z')),
            '[:upper:]' => implode('', range('A', 'Z')),
            '[:space:]' => " \t\n\r\v\f",
            '[:blank:]' => " \t",
            '[:punct:]' => '!"#$%&\'()*+,-./:;<=>?@[\]^_`{|}~',
        ];
        $value = strtr($value, $classes);
        $value = strtr($value, ['\\n' => "\n", '\\t' => "\t", '\\r' => "\r", '\\\\' => '\\']);

        return preg_replace_callback('/(.)-(.)/s', function (array $matches): string {
            $start = ord($matches[1]);
            $end = ord($matches[2]);

            return $start > $end ? $matches[0] : implode('', array_map('chr', range($start, $end)));
        }, $value) ?? $value;
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function tac(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('tac', $words);

        return ['stdout' => $this->linesOut(array_reverse($this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin)))), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function rev(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('rev', $words);
        $lines = array_map(fn (string $line): string => implode('', array_reverse(mb_str_split($line))), $this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin)));

        return ['stdout' => $this->linesOut($lines), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function nl(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('nl', $words);
        $style = $args->value('-b') ?? 't';
        if (! in_array($style, ['a', 't', 'n'], true)) {
            throw VfsError::invalid('nl -b supports a (all lines), t (non-empty lines), and n (none).');
        }
        $number = (int) ($args->value('-v') ?? 1);
        $increment = max(1, (int) ($args->value('-i') ?? 1));
        $width = max(1, (int) ($args->value('-w') ?? 6));
        $separator = $args->value('-s') ?? "\t";
        $out = [];
        foreach ($this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin)) as $line) {
            if ($style === 'n' || ($style === 't' && trim($line) === '')) {
                $out[] = str_repeat(' ', $width + strlen($separator)).$line;

                continue;
            }
            $out[] = str_pad((string) $number, $width, ' ', STR_PAD_LEFT).$separator.$line;
            $number += $increment;
        }

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * seq [FIRST [INCREMENT]] LAST; negative numbers are operands.
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function seq(array $words): array
    {
        $separator = "\n";
        $format = null;
        $width = false;
        $numbers = [];
        for ($i = 0; $i < count($words); $i++) {
            $word = $words[$i];
            if (is_numeric($word)) {
                $numbers[] = $word;
            } elseif ($word === '-w') {
                $width = true;
            } elseif ($word === '-s' || $word === '-f') {
                $value = $words[++$i] ?? throw VfsError::invalid("seq {$word} requires a value.");
                $word === '-s' ? $separator = stripcslashes($value) : $format = $value;
            } elseif (str_starts_with($word, '-s') || str_starts_with($word, '-f')) {
                str_starts_with($word, '-s') ? $separator = stripcslashes(substr($word, 2)) : $format = substr($word, 2);
            } else {
                throw VfsArgs::unsupportedFlag(\App\Domain\Vfs\Core\VfsCommandSpecRegistry::get('seq') ?? throw VfsError::invalid('seq'), $word);
            }
        }
        if ($numbers === [] || count($numbers) > 3) {
            throw VfsError::invalid('seq requires 1 to 3 numeric operands: seq [FIRST [INCREMENT]] LAST.');
        }
        $start = count($numbers) >= 2 ? (float) $numbers[0] : 1.0;
        $step = count($numbers) === 3 ? (float) $numbers[1] : 1.0;
        $end = (float) $numbers[count($numbers) - 1];
        if ($step == 0.0) {
            throw VfsError::invalid('seq increment must not be zero.');
        }
        $decimals = max(array_map(fn (string $n): int => str_contains($n, '.') ? strlen(substr($n, strpos($n, '.') + 1)) : 0, $numbers));
        $values = [];
        for ($k = 0; ; $k++) {
            $value = $start + $k * $step;
            if ($step > 0 ? $value > $end + 1e-9 : $value < $end - 1e-9) {
                break;
            }
            if (count($values) >= 10_000) {
                throw VfsError::invalid('seq output exceeds the VFS limit of 10000 values.');
            }
            $rendered = $format !== null ? \App\Domain\Vfs\Core\VfsPrintf::awk($format, [$value]) : number_format($value, $decimals, '.', '');
            $values[] = $rendered;
        }
        if ($width && $format === null) {
            $pad = max(array_map('strlen', $values ?: ['']));
            $values = array_map(fn (string $v): string => str_pad($v, $pad, '0', STR_PAD_LEFT), $values);
        }

        return ['stdout' => $values === [] ? '' : implode($separator, $values)."\n", 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function base64(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('base64', $words);
        $content = $this->inputFrom($agent, $args, $cwd, $stdin);
        if ($args->has('d', 'D')) {
            $decoded = base64_decode(preg_replace('/\s+/', '', $content) ?? $content, true);
            if ($decoded === false) {
                throw VfsError::invalid('base64: invalid input.');
            }

            return ['stdout' => $decoded, 'class' => 'read-transform'];
        }
        $wrap = $args->int('-w') ?? 76;
        $encoded = base64_encode($content);
        if ($wrap > 0 && $encoded !== '') {
            $encoded = rtrim(chunk_split($encoded, $wrap, "\n"), "\n");
        }

        return ['stdout' => $encoded === '' ? '' : $encoded."\n", 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function column(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('column', $words);
        $separator = $args->value('-s');
        $outputSeparator = $args->value('-o') ?? '  ';
        $lines = $this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin));
        if (! $args->has('t') && $separator === null) {
            return ['stdout' => $this->linesOut($lines), 'class' => 'read-transform'];
        }
        $rows = array_map(fn (string $line): array => $separator === null ? (preg_split('/\s+/', trim($line)) ?: []) : explode($separator, $line), $lines);
        $widths = [];
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($cell));
            }
        }
        $out = array_map(fn (array $row): string => rtrim(implode($outputSeparator, array_map(fn (string $cell, int $index): string => $cell.str_repeat(' ', max(0, ($widths[$index] ?? 0) - mb_strlen($cell))), $row, array_keys($row)))), $rows);

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function fold(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('fold', $words);
        $width = max(1, $args->int('-w') ?? 80);
        $out = [];
        foreach ($this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin)) as $line) {
            if ($line === '' || mb_strlen($line) <= $width) {
                $out[] = $line;

                continue;
            }
            // -s breaks at the last blank within the width; plain fold cuts at the width.
            $out[] = $args->has('s') ? wordwrap($line, $width, "\n", true) : implode("\n", mb_str_split($line, $width));
        }

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function fmt(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('fmt', $words);
        $width = max(1, $args->int('-w') ?? 75);
        $paragraphs = preg_split('/\n\s*\n/', trim($this->inputFrom($agent, $args, $cwd, $stdin))) ?: [];
        $formatted = array_map(fn (string $paragraph): string => wordwrap(preg_replace('/\s+/', ' ', trim($paragraph)) ?? trim($paragraph), $width), array_filter($paragraphs, fn (string $p): bool => trim($p) !== ''));

        return ['stdout' => $formatted === [] ? '' : implode("\n\n", $formatted)."\n", 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function expand(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('expand', $words);
        $tab = max(1, $args->int('-t') ?? 8);
        $out = [];
        foreach ($this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin)) as $line) {
            $result = '';
            $initial = true;
            foreach (mb_str_split($line) as $char) {
                if ($char === "\t" && (! $args->has('i') || $initial)) {
                    $result .= str_repeat(' ', $tab - (mb_strlen($result) % $tab));

                    continue;
                }
                if ($char !== ' ' && $char !== "\t") {
                    $initial = false;
                }
                $result .= $char;
            }
            $out[] = $result;
        }

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function unexpand(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('unexpand', $words);
        $tab = max(1, $args->int('-t') ?? 8);
        // GNU: -t implies -a. A run of 2+ blanks that reaches a tab stop
        // becomes a tab; blanks after the last stop stay spaces.
        $all = $args->has('a') || $args->provided('-t');
        $out = [];
        foreach ($this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin)) as $line) {
            $result = '';
            $pending = '';
            $column = 0;
            $leading = true;
            foreach (mb_str_split($line) as $char) {
                if ($char === ' ' && ($all || $leading)) {
                    $pending .= ' ';
                    $column++;
                    if ($column % $tab === 0) {
                        $result .= strlen($pending) > 1 ? "\t" : $pending;
                        $pending = '';
                    }

                    continue;
                }
                $result .= $pending.$char;
                $pending = '';
                $column = $char === "\t" ? (intdiv($column, $tab) + 1) * $tab : $column + 1;
                $leading = $leading && $char === "\t";
            }
            $out[] = $result.$pending;
        }

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function tsort(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('tsort', $words);
        $tokens = preg_split('/\s+/', trim($this->inputFrom($agent, $args, $cwd, $stdin))) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $t): bool => $t !== ''));
        if (count($tokens) % 2 !== 0) {
            throw VfsError::invalid('tsort: input contains an odd number of tokens.');
        }
        $edges = [];
        $nodes = [];
        for ($i = 0; $i < count($tokens); $i += 2) {
            [$from, $to] = [$tokens[$i], $tokens[$i + 1]];
            $nodes[$from] = true;
            $nodes[$to] = true;
            if ($from !== $to) {
                $edges[$from][] = $to;
            }
        }
        $visited = [];
        $temp = [];
        $out = [];
        $visit = function (string $node) use (&$visit, &$visited, &$temp, &$out, $edges): void {
            if (isset($visited[$node])) {
                return;
            }
            if (isset($temp[$node])) {
                throw VfsError::invalid('tsort: input contains a loop.');
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
            $visit((string) $node);
        }

        return ['stdout' => $this->linesOut(array_reverse($out)), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function look(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('look', $words);
        $prefix = $args->operands[0] ?? throw VfsError::invalid('look requires a prefix.');
        $ignoreCase = $args->has('f');
        $lines = array_values(array_filter(
            $this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin, 1)),
            fn (string $line): bool => $ignoreCase ? str_starts_with(strtolower($line), strtolower($prefix)) : str_starts_with($line, $prefix),
        ));

        return ['stdout' => $this->linesOut($lines), 'class' => 'read-transform', 'exit_code' => $lines === [] ? 1 : 0];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function join(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('join', $words);
        $delimiter = $args->value('-t');
        if ($delimiter === '\t') {
            $delimiter = "\t";
        }
        $both = $args->int('-j', 1);
        $leftField = ($args->int('-1', 1) ?? $both ?? 1) - 1;
        $rightField = ($args->int('-2', 1) ?? $both ?? 1) - 1;
        $paths = $this->operandPaths($args, $cwd);
        if (count($paths) !== 2) {
            throw VfsError::invalid('join requires exactly two files (- for stdin).');
        }
        $read = fn (string $path): string => $path === '-' ? (string) $stdin : $this->readFull($agent, $path);
        $split = fn (string $line): array => $delimiter === null ? (preg_split('/\s+/', trim($line)) ?: []) : explode($delimiter, $line);
        $rightByKey = [];
        foreach ($this->splitLines($read($paths[1])) as $line) {
            $row = $split($line);
            $rightByKey[$row[$rightField] ?? ''][] = $row;
        }
        $out = [];
        $glue = $delimiter ?? ' ';
        foreach ($this->splitLines($read($paths[0])) as $line) {
            $left = $split($line);
            $key = $left[$leftField] ?? '';
            foreach ($rightByKey[$key] ?? [] as $right) {
                $out[] = implode($glue, array_merge([$key], array_values(array_diff_key($left, [$leftField => true])), array_values(array_diff_key($right, [$rightField => true]))));
            }
        }

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function shuf(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('shuf', $words);
        $items = $args->has('e') ? $args->operands : $this->splitLines($this->inputFrom($agent, $args, $cwd, $stdin));
        $count = $args->int('-n');
        if ($args->has('r')) {
            $items = $items === [] ? [] : array_map(fn (): string => $items[array_rand($items)], range(1, max(1, $count ?? count($items))));
        } else {
            shuffle($items);
            if ($count !== null) {
                $items = array_slice($items, 0, $count);
            }
        }

        return ['stdout' => $items === [] ? '' : implode($args->has('z') ? "\0" : "\n", $items).($args->has('z') ? "\0" : "\n"), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function bc(array $words, ?string $stdin): array
    {
        $operands = array_values(array_filter($words, fn (string $w): bool => ! in_array($w, ['-l', '-q', '-lq', '-ql'], true)));
        $source = $stdin ?? implode(' ', $operands);
        $out = [];
        foreach (preg_split('/[\n;]/', $source) ?: [] as $expression) {
            if (trim($expression) === '' || trim($expression) === 'quit') {
                continue;
            }
            $out[] = $this->evaluateArithmeticExpression(trim($expression));
        }

        return ['stdout' => $this->linesOut($out), 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function expr(array $words): array
    {
        $value = $this->evaluateArithmeticExpression(implode(' ', $words));

        // expr exits 1 when the result is 0 or empty.
        return ['stdout' => $value."\n", 'class' => 'read-transform', 'exit_code' => ($value === '0' || $value === '') ? 1 : 0];
    }

    private function evaluateArithmeticExpression(string $expr): string
    {
        if (preg_match('/^[0-9+\-*\/().%\s]+$/', $expr) !== 1) {
            throw VfsError::unsupported('Only numeric expressions (+ - * / % and parentheses) are supported.', ['suggestion' => "Use awk 'BEGIN { print ... }' for richer math."]);
        }

        $tokens = preg_split('/\s*([()+\-*\/%])\s*|\s+/', trim($expr), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $index = 0;
        $value = $this->arithmeticSum($tokens, $index);
        if ($index !== count($tokens)) {
            throw VfsError::invalid('Invalid numeric expression.');
        }

        return floor($value) == $value ? (string) (int) $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    /**
     * @param  list<string>  $tokens
     */
    private function arithmeticSum(array $tokens, int &$index): float
    {
        $value = $this->arithmeticTerm($tokens, $index);
        while (in_array($tokens[$index] ?? null, ['+', '-'], true)) {
            $operator = $tokens[$index++];
            $right = $this->arithmeticTerm($tokens, $index);
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function arithmeticTerm(array $tokens, int &$index): float
    {
        $value = $this->arithmeticFactor($tokens, $index);
        while (in_array($tokens[$index] ?? null, ['*', '/', '%'], true)) {
            $operator = $tokens[$index++];
            $right = $this->arithmeticFactor($tokens, $index);
            if ($right == 0.0 && $operator !== '*') {
                throw VfsError::invalid('Division by zero.');
            }
            $value = match ($operator) {
                '*' => $value * $right,
                '/' => $value / $right,
                default => fmod($value, $right),
            };
        }

        return $value;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function arithmeticFactor(array $tokens, int &$index): float
    {
        $token = $tokens[$index] ?? null;
        if ($token === '+' || $token === '-') {
            $index++;

            return ($token === '-' ? -1 : 1) * $this->arithmeticFactor($tokens, $index);
        }
        if ($token === '(') {
            $index++;
            $value = $this->arithmeticSum($tokens, $index);
            if (($tokens[$index] ?? null) !== ')') {
                throw VfsError::invalid('Unclosed numeric expression group.');
            }
            $index++;

            return $value;
        }
        if ($token === null || preg_match('/^\d+(?:\.\d+)?$/', $token) !== 1) {
            throw VfsError::invalid('Invalid numeric expression.');
        }
        $index++;

        return (float) $token;
    }
}
