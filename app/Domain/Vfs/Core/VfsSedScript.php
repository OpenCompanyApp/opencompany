<?php

namespace App\Domain\Vfs\Core;

/**
 * Pure, bounded sed interpreter for the VFS shell.
 *
 * Owns parsing and running a sed program over text: addresses (N, $, /re/,
 * \cREc, first~step, addr1,addr2, addr,+N, `!`), commands s y p P d D q Q = a i
 * c n N h H g G x and `{ }` blocks, with GNU one-liner text for a/i/c.
 * Basic regular expressions are translated to PCRE (`\(` groups, `\{m,n\}`,
 * `\+`, `\?`, `\|`); -E/-r selects extended syntax.
 *
 * Does not own: reading or writing files. `sed -i` is the executor's job and
 * goes through the governed write/patch paths; this class only maps input
 * text to output text, so the same result is used for display and in-place
 * edits.
 *
 * Rejected on purpose with a specific error: branching (b, t, T, :label),
 * file I/O commands (r, R, w, W, s///w) and the e command — they either loop
 * or touch storage outside the governed paths.
 *
 * Resource invariant: MAX_STEPS bounds time, not memory. A few steps can
 * double the pattern space each time (`s/a/aa/g` repeated, `h;G`), so the
 * pattern space, the hold space and the output are each bounded by the
 * caller's byte limit (the VFS content ceiling) and checked while they grow:
 * a substitution checks its running result inside the replace callback, and
 * every append is checked before it is made. Exceeding the limit is an
 * invalid_request VfsError, never a PHP memory fatal.
 */
final class VfsSedScript
{
    /** Hard bound on executed commands per run, a guard against pathological input. */
    private const MAX_STEPS = 2_000_000;

    /** Byte limit of the run in progress (pattern space, hold space, output). */
    private int $maxBytes = PHP_INT_MAX;

    /**
     * @param  list<array<string, mixed>>  $commands
     */
    private function __construct(private readonly array $commands) {}

    /**
     * @param  list<string>  $scripts  -e scripts (or the single script operand), joined by newlines
     *
     * @throws VfsError invalid_request for syntax errors, unsupported for rejected commands
     */
    public static function parse(array $scripts, bool $extended): self
    {
        return new self((new VfsSedParser(implode("\n", $scripts), $extended))->parse());
    }

    /**
     * Whether the program can modify text (anything but pure printing/deleting
     * still counts: used to skip writes that would be no-ops is the caller's
     * job via content comparison, this is informational).
     */
    public function isEmpty(): bool
    {
        return $this->commands === [];
    }

    /**
     * Run the program over one input stream.
     *
     * $maxBytes bounds the pattern space, the hold space and the output
     * (each, independently); the executor passes the VFS content ceiling so
     * any file the VFS can store can still be edited.
     *
     * @return string the output stream
     *
     * @throws VfsError invalid_request when the step budget or $maxBytes is exceeded.
     */
    public function run(string $input, bool $quiet, int $maxBytes): string
    {
        $this->maxBytes = max(1, $maxBytes);
        $lines = $input === '' ? [] : explode("\n", $input);
        $trailingNewline = $input === '' || str_ends_with($input, "\n");
        if ($trailingNewline && $lines !== []) {
            array_pop($lines);
        }

        $out = '';
        $hold = '';
        $rangeActive = [];
        $rangeEnd = [];
        $steps = 0;
        $count = count($lines);
        $index = 0;
        $quit = false;

        while ($index < $count && ! $quit) {
            $pattern = $lines[$index];
            $lineNo = $index + 1;
            $index++;
            $append = '';
            $deleted = false;
            $restart = false;

            do {
                $restart = false;
                $pc = 0;
                $total = count($this->commands);
                while ($pc < $total) {
                    if (++$steps > self::MAX_STEPS) {
                        throw VfsError::invalid('sed program exceeded the VFS step budget.', ['hint' => 'Simplify the script or narrow the input.']);
                    }
                    $command = $this->commands[$pc];
                    $isLast = $index >= $count;
                    $matched = $this->selects($command, $pc, $pattern, $lineNo, $isLast, $rangeActive, $rangeEnd);
                    if ($command['negate']) {
                        $matched = ! $matched;
                    }
                    $name = $command['cmd'];
                    if ($name === '}') {
                        $pc++;

                        continue;
                    }
                    if (! $matched) {
                        $pc = $name === '{' ? $command['end'] + 1 : $pc + 1;

                        continue;
                    }

                    // Invariant: growth is checked every step, before the next
                    // command can double it again (s///, G, H, N, p, a, i).
                    $this->assertWithin($pattern, 'pattern space');
                    $this->assertWithin($hold, 'hold space');
                    $this->assertWithin($out, 'output');
                    $this->assertWithin($append, 'output');

                    switch ($name) {
                        case '{':
                            break;
                        case 's':
                            [$pattern, $did] = $this->substitute($command, $pattern);
                            if ($did && $command['print']) {
                                $out .= $pattern."\n";
                            }
                            break;
                        case 'y':
                            $pattern = strtr($pattern, $command['map']);
                            break;
                        case 'p':
                            $out .= $pattern."\n";
                            break;
                        case 'P':
                            $out .= explode("\n", $pattern, 2)[0]."\n";
                            break;
                        case 'd':
                            $deleted = true;
                            break 2;
                        case 'D':
                            if (str_contains($pattern, "\n")) {
                                $pattern = explode("\n", $pattern, 2)[1];
                                $restart = true;
                                $out .= $append;
                                $append = '';

                                continue 3;
                            }
                            $deleted = true;
                            break 2;
                        case 'q':
                            $quit = true;
                            break 2;
                        case 'Q':
                            $quit = true;
                            $deleted = true;
                            break 2;
                        case '=':
                            $out .= $lineNo."\n";
                            break;
                        case 'a':
                            $append .= $command['text']."\n";
                            break;
                        case 'i':
                            $out .= $command['text']."\n";
                            break;
                        case 'c':
                            // With a range, the text replaces the whole range once.
                            if ($command['a2'] === null || ! ($rangeActive[$pc] ?? false)) {
                                $out .= $command['text']."\n";
                            }
                            $deleted = true;
                            break 2;
                        case 'n':
                            if ($index >= $count) {
                                $quit = true;
                                break 2;
                            }
                            if (! $quiet) {
                                $out .= $pattern."\n";
                            }
                            $out .= $append;
                            $append = '';
                            $pattern = $lines[$index];
                            $lineNo = $index + 1;
                            $index++;
                            break;
                        case 'N':
                            if ($index >= $count) {
                                $quit = true;
                                break 2;
                            }
                            $pattern .= "\n".$lines[$index];
                            $lineNo = $index + 1;
                            $index++;
                            break;
                        case 'h':
                            $hold = $pattern;
                            break;
                        case 'H':
                            $this->assertFits(strlen($hold) + 1 + strlen($pattern), 'hold space');
                            $hold .= "\n".$pattern;
                            break;
                        case 'g':
                            $pattern = $hold;
                            break;
                        case 'G':
                            $this->assertFits(strlen($pattern) + 1 + strlen($hold), 'pattern space');
                            $pattern .= "\n".$hold;
                            break;
                        case 'x':
                            [$pattern, $hold] = [$hold, $pattern];
                            break;
                    }
                    $pc++;
                }
            } while ($restart);

            if (! $deleted && ! $quiet) {
                $this->assertFits(strlen($out) + strlen($pattern) + 1, 'output');
                $out .= $pattern."\n";
            }
            $this->assertFits(strlen($out) + strlen($append), 'output');
            $out .= $append;
        }
        $this->assertWithin($out, 'output');

        if (! $trailingNewline && str_ends_with($out, "\n") && $index >= $count) {
            $out = substr($out, 0, -1);
        }

        return $out;
    }

    /**
     * Address selection with GNU range semantics: a range starts when addr1
     * matches; addr2 is checked from the following line (a line-number addr2
     * not greater than the start line selects one line).
     *
     * @param  array<string, mixed>  $command
     * @param  array<int, bool>  $rangeActive
     * @param  array<int, int>  $rangeEnd
     */
    private function selects(array $command, int $pc, string $pattern, int $lineNo, bool $isLast, array &$rangeActive, array &$rangeEnd): bool
    {
        $a1 = $command['a1'];
        $a2 = $command['a2'];
        if ($a1 === null) {
            return true;
        }
        if ($a2 === null) {
            return $this->matchesAddress($a1, $pattern, $lineNo, $isLast);
        }

        if ($rangeActive[$pc] ?? false) {
            $ends = match ($a2['type']) {
                'line' => $lineNo >= $a2['n'],
                'plus' => $lineNo >= $rangeEnd[$pc],
                'last' => $isLast,
                default => $this->matchesAddress($a2, $pattern, $lineNo, $isLast),
            };
            if ($ends || ($a2['type'] === 'line' && $lineNo > $a2['n'])) {
                $rangeActive[$pc] = false;
            }

            return true;
        }

        if (! $this->matchesAddress($a1, $pattern, $lineNo, $isLast)) {
            return false;
        }
        $oneLine = match ($a2['type']) {
            'line' => $a2['n'] <= $lineNo,
            'plus' => $a2['n'] === 0,
            'last' => $isLast,
            default => false,
        };
        if (! $oneLine) {
            $rangeActive[$pc] = true;
            if ($a2['type'] === 'plus') {
                $rangeEnd[$pc] = $lineNo + (int) $a2['n'];
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function matchesAddress(array $address, string $pattern, int $lineNo, bool $isLast): bool
    {
        return match ($address['type']) {
            'line' => $lineNo === $address['n'],
            'last' => $isLast,
            'step' => $address['step'] <= 0 ? $lineNo === $address['first'] : ($lineNo >= $address['first'] && ($lineNo - $address['first']) % $address['step'] === 0),
            'regex' => self::pregMatch($address['re'], $pattern),
            default => false,
        };
    }

    private static function pregMatch(string $regex, string $subject): bool
    {
        $result = @preg_match($regex, $subject);
        if ($result === false) {
            throw VfsError::invalid('sed regular expression failed to run (too complex or invalid).', ['regex' => $regex]);
        }

        return $result === 1;
    }

    /**
     * @param  array<string, mixed>  $command
     * @return array{0: string, 1: bool}
     */
    private function substitute(array $command, string $pattern): array
    {
        $occurrence = $command['occurrence'];
        $global = $command['global'];
        $seen = 0;
        $did = false;
        // Running size of the substituted pattern: checked per match so a
        // global substitution cannot build a result past the byte limit.
        $size = strlen($pattern);
        $result = @preg_replace_callback($command['re'], function (array $match) use (&$seen, &$did, &$size, $occurrence, $global, $command): string {
            $seen++;
            if ($seen < $occurrence || (! $global && $seen > $occurrence)) {
                return $match[0];
            }
            $did = true;
            $replacement = self::expandReplacement($command['replacement'], $match, $this->maxBytes - $size + strlen($match[0]), $this->maxBytes);
            $size += strlen($replacement) - strlen($match[0]);
            $this->assertFits($size, 'pattern space');

            return $replacement;
        }, $pattern);
        if ($result === null) {
            throw VfsError::invalid('sed substitution failed to run (regex too complex or invalid).');
        }

        return [$result, $did];
    }

    /**
     * Fail when $bytes exceeds the run's byte limit for $what.
     *
     * @throws VfsError
     */
    private function assertFits(int $bytes, string $what): void
    {
        if ($bytes > $this->maxBytes) {
            throw VfsError::invalid("sed {$what} would exceed {$this->maxBytes} bytes.", [
                'max_bytes' => $this->maxBytes,
                'hint' => 'The script grows text without bound (e.g. a repeated s/a/aa/g or G); simplify it or narrow the input.',
            ]);
        }
    }

    private function assertWithin(string $buffer, string $what): void
    {
        $this->assertFits(strlen($buffer), $what);
    }

    /**
     * Expand a parsed replacement: list of literal strings, group references
     * (int), and case-conversion markers (\U \L \u \l \E). Fails as soon
     * as the expansion passes $remaining (what is left of the run's $limit
     * for this match), before appending further parts.
     *
     * @param  list<string|int|array{case: string}>  $parts
     * @param  array<int|string, string>  $match
     */
    private static function expandReplacement(array $parts, array $match, int $remaining, int $limit): string
    {
        $out = '';
        $mode = null;
        $next = null;
        foreach ($parts as $part) {
            if (is_array($part)) {
                $case = $part['case'];
                if ($case === 'u' || $case === 'l') {
                    $next = $case;
                } else {
                    $mode = $case === 'E' ? null : $case;
                }

                continue;
            }
            $text = is_int($part) ? (string) ($match[$part] ?? '') : $part;
            if ($mode === 'U') {
                $text = mb_strtoupper($text);
            } elseif ($mode === 'L') {
                $text = mb_strtolower($text);
            }
            if ($next !== null && $text !== '') {
                $first = mb_substr($text, 0, 1);
                $text = ($next === 'u' ? mb_strtoupper($first) : mb_strtolower($first)).mb_substr($text, 1);
                $next = null;
            }
            if (strlen($out) + strlen($text) > $remaining) {
                throw VfsError::invalid("sed pattern space would exceed {$limit} bytes.", ['max_bytes' => $limit, 'hint' => 'The substitution grows text without bound; simplify it or narrow the input.']);
            }
            $out .= $text;
        }

        return $out;
    }
}
