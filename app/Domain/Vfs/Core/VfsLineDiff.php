<?php

namespace App\Domain\Vfs\Core;

/**
 * Pure LCS line diff (Myers O(ND)) with GNU normal and unified output.
 *
 * Owns: computing a minimal edit script between two texts, optional
 * comparison normalization (-i, -w, -b, -B) that never alters the printed
 * lines, and rendering in GNU "normal" format or unified format with
 * `--- a/path` / `+++ b/path` headers and `@@ -l,s +l,s @@` hunks, including
 * the `\ No newline at end of file` marker so the output is a valid patch.
 *
 * Replaces a positional line-N-vs-line-N comparison that reported every line
 * after an insertion as changed.
 *
 * Bounded: refuses inputs whose edit distance exceeds MAX_EDITS instead of
 * consuming unbounded memory.
 */
final class VfsLineDiff
{
    private const MAX_EDITS = 20_000;

    /** @var list<string> */
    private array $a;

    /** @var list<string> */
    private array $b;

    private bool $aNewline;

    private bool $bNewline;

    /** @var list<array{0: string, 1: int, 2: int}> Edit script: ['=', ai, bi] | ['-', ai, -1] | ['+', -1, bi] */
    private array $ops = [];

    /**
     * @param  array{ignore_case?: bool, ignore_all_space?: bool, ignore_space_change?: bool, ignore_blank_lines?: bool}  $options
     */
    public function __construct(string $left, string $right, private readonly array $options = [])
    {
        [$this->a, $this->aNewline] = self::split($left);
        [$this->b, $this->bNewline] = self::split($right);
        $this->ops = $this->compute();
    }

    public function identical(): bool
    {
        foreach ($this->hunks(0) as $hunk) {
            return false;
        }

        return true;
    }

    /**
     * GNU normal format (`2c2`, `< old`, `---`, `> new`).
     */
    public function normal(): string
    {
        $out = '';
        foreach ($this->hunks(0) as $hunk) {
            $deleted = array_values(array_filter($hunk, fn (array $op): bool => $op[0] === '-'));
            $added = array_values(array_filter($hunk, fn (array $op): bool => $op[0] === '+'));
            $aStart = $deleted !== [] ? $deleted[0][1] + 1 : $this->positionBefore($hunk, 'a');
            $bStart = $added !== [] ? $added[0][2] + 1 : $this->positionBefore($hunk, 'b');
            $aRange = $deleted === [] ? (string) $aStart : self::range($aStart, count($deleted));
            $bRange = $added === [] ? (string) $bStart : self::range($bStart, count($added));
            $kind = $deleted === [] ? 'a' : ($added === [] ? 'd' : 'c');
            $out .= $aRange.$kind.$bRange."\n";
            foreach ($deleted as $op) {
                $out .= '< '.$this->a[$op[1]]."\n".$this->noNewlineMarker('a', $op[1]);
            }
            if ($kind === 'c') {
                $out .= "---\n";
            }
            foreach ($added as $op) {
                $out .= '> '.$this->b[$op[2]]."\n".$this->noNewlineMarker('b', $op[2]);
            }
        }

        return $out;
    }

    /**
     * Unified format with $context lines of context.
     */
    public function unified(string $leftLabel, string $rightLabel, int $context = 3): string
    {
        $hunks = $this->hunks($context);
        if ($hunks === []) {
            return '';
        }
        $out = "--- {$leftLabel}\n+++ {$rightLabel}\n";
        foreach ($hunks as $hunk) {
            $aLines = array_values(array_filter($hunk, fn (array $op): bool => $op[0] !== '+'));
            $bLines = array_values(array_filter($hunk, fn (array $op): bool => $op[0] !== '-'));
            $aStart = $aLines !== [] ? $aLines[0][1] + 1 : $this->positionBefore($hunk, 'a');
            $bStart = $bLines !== [] ? $bLines[0][2] + 1 : $this->positionBefore($hunk, 'b');
            $out .= '@@ -'.self::unifiedRange($aStart, count($aLines)).' +'.self::unifiedRange($bStart, count($bLines))." @@\n";
            foreach ($hunk as $op) {
                if ($op[0] === '=') {
                    $out .= ' '.$this->a[$op[1]]."\n".$this->noNewlineMarker('a', $op[1]);
                } elseif ($op[0] === '-') {
                    $out .= '-'.$this->a[$op[1]]."\n".$this->noNewlineMarker('a', $op[1]);
                } else {
                    $out .= '+'.$this->b[$op[2]]."\n".$this->noNewlineMarker('b', $op[2]);
                }
            }
        }

        return $out;
    }

    /**
     * Group the edit script into hunks with up to $context surrounding
     * unchanged lines; adjacent hunks closer than 2*context merge.
     *
     * @return list<list<array{0: string, 1: int, 2: int}>>
     */
    private function hunks(int $context): array
    {
        $changes = [];
        foreach ($this->ops as $index => $op) {
            if ($op[0] !== '=') {
                $changes[] = $index;
            }
        }
        if ($changes === []) {
            return [];
        }

        // Cluster change indexes: a new cluster starts when the gap of
        // unchanged ops exceeds 2*context (or any gap for normal format).
        $clusters = [];
        $current = [$changes[0], $changes[0]];
        foreach (array_slice($changes, 1) as $index) {
            if ($index - $current[1] - 1 > ($context === 0 ? 0 : 2 * $context)) {
                $clusters[] = $current;
                $current = [$index, $index];
            } else {
                $current[1] = $index;
            }
        }
        $clusters[] = $current;

        $hunks = [];
        foreach ($clusters as [$first, $last]) {
            $start = max(0, $first - $context);
            $end = min(count($this->ops) - 1, $last + $context);
            $hunk = array_slice($this->ops, $start, $end - $start + 1);
            if (($this->options['ignore_blank_lines'] ?? false) && $this->onlyBlankChanges($hunk)) {
                continue;
            }
            $hunks[] = $hunk;
        }

        return $hunks;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $hunk
     */
    private function onlyBlankChanges(array $hunk): bool
    {
        foreach ($hunk as $op) {
            if ($op[0] === '-' && trim($this->a[$op[1]]) !== '') {
                return false;
            }
            if ($op[0] === '+' && trim($this->b[$op[2]]) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * The 1-based line number in side $side just before a hunk with no lines
     * on that side (GNU prints the preceding line number, 0 at the start).
     *
     * @param  list<array{0: string, 1: int, 2: int}>  $hunk
     */
    private function positionBefore(array $hunk, string $side): int
    {
        $firstIndex = array_search($hunk[0], $this->ops, true);
        for ($i = (int) $firstIndex - 1; $i >= 0; $i--) {
            $op = $this->ops[$i];
            if ($side === 'a' && $op[1] >= 0) {
                return $op[1] + 1;
            }
            if ($side === 'b' && $op[2] >= 0) {
                return $op[2] + 1;
            }
        }

        return 0;
    }

    private function noNewlineMarker(string $side, int $index): string
    {
        $lines = $side === 'a' ? $this->a : $this->b;
        $hasNewline = $side === 'a' ? $this->aNewline : $this->bNewline;

        return (! $hasNewline && $index === count($lines) - 1) ? "\\ No newline at end of file\n" : '';
    }

    private static function range(int $start, int $count): string
    {
        return $count <= 1 ? (string) $start : $start.','.($start + $count - 1);
    }

    private static function unifiedRange(int $start, int $count): string
    {
        if ($count === 0) {
            return $start.',0';
        }

        return $count === 1 ? (string) $start : $start.','.$count;
    }

    /**
     * @return array{0: list<string>, 1: bool}
     */
    private static function split(string $text): array
    {
        if ($text === '') {
            return [[], true];
        }
        $newline = str_ends_with($text, "\n");
        $lines = explode("\n", $newline ? substr($text, 0, -1) : $text);

        return [$lines, $newline];
    }

    private function key(string $line): string
    {
        if ($this->options['ignore_case'] ?? false) {
            $line = mb_strtolower($line);
        }
        if ($this->options['ignore_all_space'] ?? false) {
            $line = preg_replace('/[ \t\r\f\v]+/', '', $line) ?? $line;
        } elseif ($this->options['ignore_space_change'] ?? false) {
            $line = rtrim(preg_replace('/[ \t\r\f\v]+/', ' ', $line) ?? $line);
        }

        return $line;
    }

    /**
     * Myers greedy shortest edit script, after trimming the common prefix and
     * suffix (which keeps typical "small edit in a big file" diffs cheap).
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function compute(): array
    {
        $a = array_map(fn (string $line): string => $this->key($line), $this->a);
        $b = array_map(fn (string $line): string => $this->key($line), $this->b);
        $n = count($a);
        $m = count($b);
        // Lines are equal only when the trailing-newline state also matches
        // for the final line, so "x" vs "x\n" is a real (patchable) change.
        if ($n > 0 && $m > 0 && $this->aNewline !== $this->bNewline) {
            $a[$n - 1] .= $this->aNewline ? '' : "\0eof";
            $b[$m - 1] .= $this->bNewline ? '' : "\0eof";
        }

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
            $ops[] = ['=', $i, $i];
        }
        $middle = $this->myers(array_slice($a, $prefix, $n - $prefix - $suffix), array_slice($b, $prefix, $m - $prefix - $suffix));
        foreach ($middle as [$kind, $i, $j]) {
            $ops[] = [$kind, $i < 0 ? -1 : $i + $prefix, $j < 0 ? -1 : $j + $prefix];
        }
        for ($k = 0; $k < $suffix; $k++) {
            $ops[] = ['=', $n - $suffix + $k, $m - $suffix + $k];
        }

        return $ops;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function myers(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        if ($n === 0 && $m === 0) {
            return [];
        }
        if ($n === 0) {
            return array_map(fn (int $j): array => ['+', -1, $j], range(0, $m - 1));
        }
        if ($m === 0) {
            return array_map(fn (int $i): array => ['-', $i, -1], range(0, $n - 1));
        }

        $max = $n + $m;
        $v = [1 => 0];
        $trace = [];
        for ($d = 0; $d <= $max; $d++) {
            if ($d > self::MAX_EDITS) {
                throw VfsError::invalid('diff: the files differ too much to compare within the VFS budget.', ['hint' => 'Compare smaller sections (head/sed -n) or use cmp/sha256sum to check equality.']);
            }
            $trace[] = $v;
            for ($k = -$d; $k <= $d; $k += 2) {
                if ($k === -$d || ($k !== $d && ($v[$k - 1] ?? -1) < ($v[$k + 1] ?? -1))) {
                    $x = $v[$k + 1] ?? 0;
                } else {
                    $x = ($v[$k - 1] ?? 0) + 1;
                }
                $y = $x - $k;
                while ($x < $n && $y < $m && $a[$x] === $b[$y]) {
                    $x++;
                    $y++;
                }
                $v[$k] = $x;
                if ($x >= $n && $y >= $m) {
                    return $this->backtrack($trace, $n, $m, $a, $b);
                }
            }
        }

        return [];
    }

    /**
     * @param  list<array<int, int>>  $trace
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function backtrack(array $trace, int $n, int $m, array $a, array $b): array
    {
        $ops = [];
        $x = $n;
        $y = $m;
        for ($d = count($trace) - 1; $d >= 0; $d--) {
            $v = $trace[$d];
            $k = $x - $y;
            if ($k === -$d || ($k !== $d && ($v[$k - 1] ?? -1) < ($v[$k + 1] ?? -1))) {
                $prevK = $k + 1;
            } else {
                $prevK = $k - 1;
            }
            $prevX = $v[$prevK] ?? 0;
            $prevY = $prevX - $prevK;
            while ($x > $prevX && $y > $prevY) {
                $ops[] = ['=', $x - 1, $y - 1];
                $x--;
                $y--;
            }
            if ($d > 0) {
                if ($x === $prevX) {
                    $ops[] = ['+', -1, $y - 1];
                } else {
                    $ops[] = ['-', $x - 1, -1];
                }
            }
            $x = $prevX;
            $y = $prevY;
        }

        return array_reverse($ops);
    }
}
