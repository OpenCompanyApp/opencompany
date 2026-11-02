<?php

namespace App\Domain\Vfs\Core;

/**
 * C-style printf formatting shared by the shell `printf` builtin and awk.
 *
 * Owns conversion specs `%[flags][width][.precision]conv` with flags `-+ 0#`,
 * `*` width/precision, conversions d i o u x X e E f F g G c s b q and `%%`.
 * PHP's sprintf differs from C in exponent digits, the space and `#` flags,
 * and argument-count errors (it throws ArgumentCountError for too few args),
 * so every conversion is formatted here explicitly and never throws on
 * argument count: missing arguments format as "" or 0, like bash.
 *
 * Resource invariant: every width and precision is checked against
 * MAX_FIELD before anything is padded, and the bytes one invocation
 * produces are counted against MAX_OUTPUT_BYTES as they are appended, so a
 * tiny format such as `%100000000s` fails with a VfsError instead of
 * allocating past the worker's memory limit. The executor's own output cap
 * runs only after the command returns; it cannot protect this allocation.
 *
 * Pure: no storage access.
 */
final class VfsPrintf
{
    /** Largest accepted field width or precision (bytes/characters). */
    public const MAX_FIELD = 1_048_576;

    /** Largest output one printf/sprintf invocation may build. */
    public const MAX_OUTPUT_BYTES = 4_194_304;

    private const SPEC = '/%(?<flags>[-+ 0#\']*)(?<width>\*|\d+)?(?:\.(?<precision>\*|\d*))?(?<conv>[diouxXeEfFgGcsbq%])/';

    /**
     * bash `printf`: process escapes in the format, then re-apply the format
     * while arguments remain (each pass must consume at least one argument).
     * A `\c` inside a `%b` argument ends the whole invocation: nothing after
     * it is written, neither the rest of the format nor further passes.
     *
     * @param  list<string>  $args
     *
     * @throws VfsError invalid_request when a field or the output exceeds its cap.
     */
    public static function shell(string $format, array $args): string
    {
        $format = self::escapes($format, false);
        $out = '';
        $index = 0;
        $count = count($args);
        $stopped = false;
        do {
            $before = $index;
            $out .= self::apply($format, $args, $index, true, $stopped, strlen($out));
            if ($stopped || $index === $before) {
                break;
            }
        } while ($index < $count);

        return $out;
    }

    /**
     * awk `printf`/`sprintf`: one pass, escapes already resolved by the awk
     * lexer.
     *
     * @param  list<string|int|float|null>  $args
     *
     * @throws VfsError invalid_request when a field or the output exceeds its cap.
     */
    public static function awk(string $format, array $args): string
    {
        $index = 0;
        $stopped = false;

        return self::apply($format, $args, $index, false, $stopped, 0);
    }

    /**
     * One pass of $format over $args starting at $index. Sets $stopped when
     * a `%b` argument contained `\c` (shell only); the output then ends
     * with that conversion. $already is the output size of earlier passes,
     * counted against MAX_OUTPUT_BYTES.
     *
     * @param  list<string|int|float|null>  $args
     */
    private static function apply(string $format, array $args, int &$index, bool $shell, bool &$stopped, int $already): string
    {
        preg_match_all(self::SPEC, $format, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $out = '';
        $position = 0;
        $append = function (string $text) use (&$out, $already): void {
            if ($already + strlen($out) + strlen($text) > self::MAX_OUTPUT_BYTES) {
                throw VfsError::invalid('printf output exceeds '.self::MAX_OUTPUT_BYTES.' bytes.', ['hint' => 'Use smaller widths or fewer arguments.']);
            }
            $out .= $text;
        };
        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $append(substr($format, $position, $offset - $position));
            $position = $offset + strlen($whole);
            $conv = $match['conv'][0];
            if ($conv === '%') {
                $append('%');

                continue;
            }
            $flags = $match['flags'][0];
            // Unmatched groups before `conv` are present as ['', -1].
            $width = $match['width'][0];
            if ($width === '*') {
                $width = (string) (int) self::number($args[$index++] ?? 0);
                if (str_starts_with($width, '-')) {
                    $flags .= '-';
                    $width = substr($width, 1);
                }
            }
            // `%.f` means precision 0; no dot means no precision.
            $precision = str_contains($whole, '.') ? ($match['precision'][0] === '' ? '0' : $match['precision'][0]) : null;
            if ($precision === '*') {
                $precision = (string) max(0, (int) self::number($args[$index++] ?? 0));
            }
            // Invariant: checked before convert() pads or formats anything.
            foreach (['width' => $width, 'precision' => $precision] as $label => $value) {
                if ($value !== null && $value !== '' && (strlen($value) > 7 || (int) $value > self::MAX_FIELD)) {
                    throw VfsError::invalid("printf {$label} {$value} exceeds the maximum of ".self::MAX_FIELD.'.', ['hint' => 'Use a smaller field width or precision.']);
                }
            }
            $arg = $args[$index] ?? null;
            $index++;

            $append(self::convert($conv, $flags, $width === '' ? null : (int) $width, $precision === null ? null : (int) $precision, $arg, $shell, $stopped));
            if ($stopped) {
                return $out;
            }
        }
        $append(substr($format, $position));

        return $out;
    }

    private static function convert(string $conv, string $flags, ?int $width, ?int $precision, string|int|float|null $arg, bool $shell, bool &$stopped): string
    {
        $left = str_contains($flags, '-');
        $zero = str_contains($flags, '0') && ! $left;
        $plus = str_contains($flags, '+');
        $space = str_contains($flags, ' ');
        $alt = str_contains($flags, '#');

        switch ($conv) {
            case 's':
                $text = self::string($arg);
                if ($precision !== null) {
                    $text = mb_substr($text, 0, $precision);
                }

                return self::pad($text, $width, $left, false);
            case 'q':
                if (! $shell) {
                    throw VfsError::invalid('awk printf does not support %q.', ['hint' => 'Use %s.']);
                }
                $text = self::shellQuote(self::string($arg));
                if ($precision !== null) {
                    $text = substr($text, 0, $precision);
                }

                return self::pad($text, $width, $left, false);
            case 'b':
                $text = self::escapes(self::string($arg), true, $stopped);
                if ($precision !== null) {
                    $text = substr($text, 0, $precision);
                }

                return self::pad($text, $width, $left, false);
            case 'c':
                if (is_int($arg) || is_float($arg)) {
                    $text = mb_chr((int) $arg) ?: '';
                } else {
                    $text = mb_substr(self::string($arg), 0, 1);
                }

                return self::pad($text, $width, $left, false);
            case 'd':
            case 'i':
            case 'u':
                $value = (int) self::number($arg);
                $digits = (string) abs($value);
                if ($precision !== null) {
                    $digits = str_pad($digits, $precision, '0', STR_PAD_LEFT);
                    $zero = false;
                }

                return self::signed($digits, $value < 0, $plus, $space, $width, $left, $zero);
            case 'o':
            case 'x':
            case 'X':
                $value = (int) self::number($arg);
                $digits = match ($conv) {
                    'o' => decoct($value),
                    'x' => dechex($value),
                    default => strtoupper(dechex($value)),
                };
                if ($precision !== null) {
                    $digits = str_pad($digits, $precision, '0', STR_PAD_LEFT);
                    $zero = false;
                }
                if ($alt && $value !== 0) {
                    $digits = ($conv === 'o' ? '0' : ($conv === 'x' ? '0x' : '0X')).$digits;
                }

                return self::pad($digits, $width, $left, $zero);
            default:
                $value = self::number($arg);
                $body = self::floatBody($conv, abs($value), $precision ?? 6, $alt);

                return self::signed($body, $value < 0, $plus, $space, $width, $left, $zero);
        }
    }

    private static function floatBody(string $conv, float $value, int $precision, bool $alt): string
    {
        if (is_nan($value)) {
            return ctype_upper($conv) ? 'NAN' : 'nan';
        }
        if (is_infinite($value)) {
            return ctype_upper($conv) ? 'INF' : 'inf';
        }
        $lower = strtolower($conv);
        if ($lower === 'f') {
            return number_format($value, $precision, '.', '');
        }
        if ($lower === 'e') {
            return self::exponent($value, $precision, $conv === 'E');
        }

        // %g: shortest of %e/%f by C rules, trailing zeros removed unless #.
        $p = $precision === 0 ? 1 : $precision;
        $exp = $value == 0.0 ? 0 : (int) floor(log10($value));
        $rounded = (float) sprintf('%.'.max(0, $p - 1).'e', $value);
        if ($rounded != 0.0) {
            $exp = (int) floor(log10($rounded));
        }
        if ($exp < -4 || $exp >= $p) {
            $text = self::exponent($value, $p - 1, $conv === 'G');
            if (! $alt) {
                $text = preg_replace('/\.?0+(?=[eE])/', '', $text) ?? $text;
            }

            return $text;
        }
        $text = number_format($value, max(0, $p - 1 - $exp), '.', '');
        if (! $alt && str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $text;
    }

    private static function exponent(float $value, int $precision, bool $upper): string
    {
        $text = sprintf('%.'.$precision.'e', $value);
        $text = preg_replace_callback('/e([+-])(\d+)$/', fn (array $m): string => 'e'.$m[1].str_pad($m[2], 2, '0', STR_PAD_LEFT), $text) ?? $text;

        return $upper ? strtoupper($text) : $text;
    }

    private static function signed(string $digits, bool $negative, bool $plus, bool $space, ?int $width, bool $left, bool $zero): string
    {
        $sign = $negative ? '-' : ($plus ? '+' : ($space ? ' ' : ''));
        if ($zero && $width !== null && strlen($sign.$digits) < $width) {
            $digits = str_pad($digits, $width - strlen($sign), '0', STR_PAD_LEFT);
        }

        return self::pad($sign.$digits, $width, $left, false);
    }

    private static function pad(string $text, ?int $width, bool $left, bool $zero): string
    {
        if ($width === null) {
            return $text;
        }
        $length = mb_strlen($text);
        if ($length >= $width) {
            return $text;
        }

        return $left ? $text.str_repeat(' ', $width - $length) : str_repeat($zero ? '0' : ' ', $width - $length).$text;
    }

    public static function number(string|int|float|null $value): float|int
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = trim((string) $value);
        if (preg_match('/^[+-]?0x[0-9a-f]+$/i', $value) === 1) {
            return (int) hexdec(ltrim($value, '+-')) * (str_starts_with($value, '-') ? -1 : 1);
        }
        if (preg_match('/^[+-]?(\d+\.?\d*(e[+-]?\d+)?|\.\d+(e[+-]?\d+)?)/i', $value, $m) === 1) {
            $number = $m[0];

            return str_contains($number, '.') || stripos($number, 'e') !== false ? (float) $number : (int) $number;
        }
        if (str_starts_with($value, "'") || str_starts_with($value, '"')) {
            // bash: a leading quote yields the character code.
            return mb_ord(mb_substr($value, 1, 1) ?: "\0") ?: 0;
        }

        return 0;
    }

    private static function string(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_float($value)) {
            return floor($value) == $value && abs($value) < 1e16 ? (string) (int) $value : (string) $value;
        }

        return (string) $value;
    }

    /**
     * Quote one argument for `%q` so the VFS shell lexer reads it back as
     * exactly one word with the same bytes.
     *
     * - empty: `''`;
     * - only portable word characters: unchanged;
     * - printable text: each other byte backslash-escaped (bash style,
     *   `a b` becomes `a\ b`);
     * - text with control bytes (newline, tab, ...): POSIX single quotes,
     *   with `'` written as `'\''`. bash would write `$'...'`, but the VFS
     *   lexer has no `$'...'` quoting, and a single-quoted newline is the
     *   same word in every POSIX shell.
     */
    private static function shellQuote(string $text): string
    {
        if ($text === '') {
            return "''";
        }
        if (preg_match('/^[A-Za-z0-9_.\/:@%+=,-]+$/', $text) === 1) {
            return $text;
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $text) === 1) {
            return "'".str_replace("'", "'\\''", $text)."'";
        }

        return preg_replace('/([^A-Za-z0-9_.\/:@%+=,\x80-\xff-])/', '\\\\$1', $text) ?? $text;
    }

    /**
     * Resolve backslash escapes as bash printf does in formats (and %b
     * arguments, where \c stops output and octal is written \0NNN).
     * $stopped is set when a %b argument's `\c` ended the output; the
     * caller must then stop the whole printf invocation.
     */
    public static function escapes(string $text, bool $percentB, bool &$stopped = false): string
    {
        $out = '';
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $c = $text[$i];
            if ($c !== '\\' || $i + 1 >= $length) {
                $out .= $c;

                continue;
            }
            $n = $text[++$i];
            switch ($n) {
                case 'n': $out .= "\n";
                    break;
                case 't': $out .= "\t";
                    break;
                case 'r': $out .= "\r";
                    break;
                case 'a': $out .= "\x07";
                    break;
                case 'b': $out .= "\x08";
                    break;
                case 'f': $out .= "\f";
                    break;
                case 'v': $out .= "\v";
                    break;
                case 'e': $out .= "\x1b";
                    break;
                case '\\': $out .= '\\';
                    break;
                case '"': $out .= '"';
                    break;
                case "'": $out .= "'";
                    break;
                case 'c':
                    if ($percentB) {
                        $stopped = true;

                        return $out;
                    }
                    $out .= '\\c';
                    break;
                case 'x':
                    $hex = '';
                    while (strlen($hex) < 2 && $i + 1 < $length && ctype_xdigit($text[$i + 1])) {
                        $hex .= $text[++$i];
                    }
                    $out .= $hex === '' ? '\\x' : chr((int) hexdec($hex));
                    break;
                default:
                    if ($n >= '0' && $n <= '7') {
                        $octal = $n;
                        $max = ($percentB && $n === '0') ? 4 : 3;
                        while (strlen($octal) < $max && $i + 1 < $length && $text[$i + 1] >= '0' && $text[$i + 1] <= '7') {
                            $octal .= $text[++$i];
                        }
                        $out .= chr((int) octdec($octal) & 0xFF);
                    } else {
                        $out .= '\\'.$n;
                    }
            }
        }

        return $out;
    }
}
