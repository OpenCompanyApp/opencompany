<?php

namespace App\Domain\Vfs\Core;

/**
 * C-style printf formatting shared by the shell `printf` builtin and awk.
 *
 * Owns conversion specs `%[flags][width][.precision]conv` with flags `-+ 0#`,
 * `*` width/precision, conversions d i o u x X e E f F g G c s b and `%%`.
 * PHP's sprintf differs from C in exponent digits, the space and `#` flags,
 * and argument-count errors (it throws ArgumentCountError for too few args),
 * so every conversion is formatted here explicitly and never throws on
 * argument count: missing arguments format as "" or 0, like bash.
 *
 * Pure: no storage access.
 */
final class VfsPrintf
{
    private const SPEC = '/%(?<flags>[-+ 0#\']*)(?<width>\*|\d+)?(?:\.(?<precision>\*|\d*))?(?<conv>[diouxXeEfFgGcsbq%])/';

    /**
     * bash `printf`: process escapes in the format, then re-apply the format
     * while arguments remain (each pass must consume at least one argument).
     *
     * @param  list<string>  $args
     */
    public static function shell(string $format, array $args): string
    {
        $format = self::escapes($format, false);
        $out = '';
        $index = 0;
        $count = count($args);
        do {
            $before = $index;
            $out .= self::apply($format, $args, $index, true);
            if ($index === $before) {
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
     */
    public static function awk(string $format, array $args): string
    {
        $index = 0;

        return self::apply($format, $args, $index, false);
    }

    /**
     * @param  list<string|int|float|null>  $args
     */
    private static function apply(string $format, array $args, int &$index, bool $shell): string
    {
        return preg_replace_callback(self::SPEC, function (array $m) use ($args, &$index, $shell): string {
            if ($m['conv'] === '%') {
                return '%';
            }
            $flags = $m['flags'];
            $width = $m['width'];
            if ($width === '*') {
                $width = (string) (int) self::number($args[$index++] ?? 0);
                if (str_starts_with($width, '-')) {
                    $flags .= '-';
                    $width = substr($width, 1);
                }
            }
            // `%.f` means precision 0; no dot means no precision.
            $precision = str_contains($m[0], '.') ? ($m['precision'] === '' ? '0' : $m['precision']) : null;
            if ($precision === '*') {
                $precision = (string) max(0, (int) self::number($args[$index++] ?? 0));
            }
            $arg = $args[$index] ?? null;
            $index++;

            return self::convert($m['conv'], $flags, $width === '' ? null : (int) $width, $precision === null ? null : (int) $precision, $arg, $shell);
        }, $format) ?? $format;
    }

    private static function convert(string $conv, string $flags, ?int $width, ?int $precision, string|int|float|null $arg, bool $shell): string
    {
        $left = str_contains($flags, '-');
        $zero = str_contains($flags, '0') && ! $left;
        $plus = str_contains($flags, '+');
        $space = str_contains($flags, ' ');
        $alt = str_contains($flags, '#');

        switch ($conv) {
            case 's':
            case 'q':
                $text = self::string($arg);
                if ($precision !== null) {
                    $text = mb_substr($text, 0, $precision);
                }

                return self::pad($text, $width, $left, false);
            case 'b':
                $text = self::escapes(self::string($arg), true);
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
     * Resolve backslash escapes as bash printf does in formats (and %b
     * arguments, where \c stops output and octal is written \0NNN).
     */
    public static function escapes(string $text, bool $percentB): string
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
                case 'n': $out .= "\n"; break;
                case 't': $out .= "\t"; break;
                case 'r': $out .= "\r"; break;
                case 'a': $out .= "\x07"; break;
                case 'b': $out .= "\x08"; break;
                case 'f': $out .= "\f"; break;
                case 'v': $out .= "\v"; break;
                case 'e': $out .= "\x1b"; break;
                case '\\': $out .= '\\'; break;
                case '"': $out .= '"'; break;
                case "'": $out .= "'"; break;
                case 'c':
                    if ($percentB) {
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
