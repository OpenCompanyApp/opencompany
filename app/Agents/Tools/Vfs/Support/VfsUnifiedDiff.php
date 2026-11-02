<?php

namespace App\Agents\Tools\Vfs\Support;

/**
 * Minimal line-based unified diff for vfs_edit dry runs.
 *
 * Output is standard unified format (`--- a/path`, `+++ b/path`, `@@` hunks
 * with 3 context lines, `\ No newline at end of file`) so agents can read it
 * like `diff -u`. Common leading/trailing lines are trimmed before an LCS over
 * the changed middle; a middle too large for a bounded LCS degrades to one
 * delete+insert block (still a valid diff, just less minimal). Output is
 * capped in bytes with an explicit truncation line; it is a preview, never
 * applied back.
 */
final class VfsUnifiedDiff
{
    private const CONTEXT = 3;

    private const MAX_LCS_CELLS = 250_000; // ~500x500 changed lines; bounds memory of the PHP table

    public static function render(string $path, string $old, string $new, int $maxBytes = 20_000): string
    {
        if ($old === $new) {
            return '';
        }

        [$a, $aNoEol] = self::lines($old);
        [$b, $bNoEol] = self::lines($new);
        $ops = self::operations($a, $b);

        $out = ['--- a'.$path, '+++ b'.$path];
        foreach (self::hunks($ops) as $hunk) {
            $out[] = $hunk['header'];
            foreach ($hunk['lines'] as [$tag, $text, $oldIndex, $newIndex]) {
                $out[] = $tag.$text;
                $lastOld = $oldIndex !== null && $oldIndex === count($a) - 1;
                $lastNew = $newIndex !== null && $newIndex === count($b) - 1;
                if (($tag !== '+' && $lastOld && $aNoEol) || ($tag !== '-' && $lastNew && $bNoEol)) {
                    $out[] = '\\ No newline at end of file';
                }
            }
        }

        $diff = implode("\n", $out)."\n";
        if (strlen($diff) <= $maxBytes) {
            return $diff;
        }

        $cut = mb_strcut($diff, 0, $maxBytes, 'UTF-8');
        $newline = strrpos($cut, "\n");

        return ($newline !== false ? substr($cut, 0, $newline + 1) : $cut)
            ."[diff truncated at {$maxBytes} bytes of ".strlen($diff).']'."\n";
    }

    /**
     * @return array{0: list<string>, 1: bool} lines without terminators, and whether the last line lacks one
     */
    private static function lines(string $text): array
    {
        if ($text === '') {
            return [[], false];
        }
        $lines = explode("\n", $text);
        $noEol = end($lines) !== '';
        if (! $noEol) {
            array_pop($lines);
        }

        return [$lines, $noEol];
    }

    /**
     * Edit script: [tag, text, oldIndex|null, newIndex|null] with tag ' ', '-' or '+'.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: string, 1: string, 2: int|null, 3: int|null}>
     */
    private static function operations(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $prefix = 0;
        while ($prefix < $n && $prefix < $m && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < $n - $prefix && $suffix < $m - $prefix && $a[$n - 1 - $suffix] === $b[$m - 1 - $suffix]) {
            $suffix++;
        }

        $ops = [];
        for ($i = 0; $i < $prefix; $i++) {
            $ops[] = [' ', $a[$i], $i, $i];
        }

        $midA = array_slice($a, $prefix, $n - $prefix - $suffix);
        $midB = array_slice($b, $prefix, $m - $prefix - $suffix);
        foreach (self::middle($midA, $midB) as [$tag, $text, $i, $j]) {
            $ops[] = [$tag, $text, $i === null ? null : $i + $prefix, $j === null ? null : $j + $prefix];
        }

        for ($k = $suffix; $k > 0; $k--) {
            $ops[] = [' ', $a[$n - $k], $n - $k, $m - $k];
        }

        return $ops;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: string, 1: string, 2: int|null, 3: int|null}>
     */
    private static function middle(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        if ($n * $m > self::MAX_LCS_CELLS) {
            return [
                ...array_map(fn (string $line, int $i): array => ['-', $line, $i, null], $a, array_keys($a)),
                ...array_map(fn (string $line, int $j): array => ['+', $line, null, $j], $b, array_keys($b)),
            ];
        }

        // Suffix LCS lengths, one row at a time from the end.
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = [' ', $a[$i], $i++, $j++];
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $ops[] = ['-', $a[$i], $i++, null];
            } else {
                $ops[] = ['+', $b[$j], null, $j++];
            }
        }
        while ($i < $n) {
            $ops[] = ['-', $a[$i], $i++, null];
        }
        while ($j < $m) {
            $ops[] = ['+', $b[$j], null, $j++];
        }

        return $ops;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int|null, 3: int|null}>  $ops
     * @return list<array{header: string, lines: list<array{0: string, 1: string, 2: int|null, 3: int|null}>}>
     */
    private static function hunks(array $ops): array
    {
        $changed = [];
        foreach ($ops as $index => $op) {
            if ($op[0] !== ' ') {
                $changed[] = $index;
            }
        }

        // Group change indexes whose context windows touch.
        $ranges = [];
        foreach ($changed as $index) {
            $from = max(0, $index - self::CONTEXT);
            $to = min(count($ops) - 1, $index + self::CONTEXT);
            if ($ranges !== [] && $from <= $ranges[count($ranges) - 1][1] + 1) {
                $ranges[count($ranges) - 1][1] = $to;
            } else {
                $ranges[] = [$from, $to];
            }
        }

        $hunks = [];
        foreach ($ranges as [$from, $to]) {
            $lines = array_slice($ops, $from, $to - $from + 1);
            $oldStart = null;
            $newStart = null;
            $oldCount = 0;
            $newCount = 0;
            foreach ($lines as $op) {
                if ($op[0] !== '+') {
                    $oldStart ??= $op[2];
                    $oldCount++;
                }
                if ($op[0] !== '-') {
                    $newStart ??= $op[3];
                    $newCount++;
                }
            }
            $oldStart = $oldStart === null ? self::anchor($ops, $from, 2) : $oldStart + 1;
            $newStart = $newStart === null ? self::anchor($ops, $from, 3) : $newStart + 1;
            $hunks[] = [
                'header' => "@@ -{$oldStart},{$oldCount} +{$newStart},{$newCount} @@",
                'lines' => $lines,
            ];
        }

        return $hunks;
    }

    /**
     * Unified-diff start line for an empty side: the line after which the
     * hunk applies (0 at the top of the file).
     *
     * @param  list<array{0: string, 1: string, 2: int|null, 3: int|null}>  $ops
     */
    private static function anchor(array $ops, int $from, int $side): int
    {
        for ($k = $from - 1; $k >= 0; $k--) {
            if ($ops[$k][$side] !== null) {
                return $ops[$k][$side] + 1;
            }
        }

        return 0;
    }
}
