<?php

namespace App\Domain\Vfs\Core;

/**
 * Parser for VfsSedScript programs (see that class for the supported language).
 *
 * Produces a flat command list; `{` records the index of its matching `}` so
 * the interpreter can skip a block whose address does not select the line.
 * Regular expressions are compiled to PCRE once here.
 *
 * @internal used only by VfsSedScript
 */
final class VfsSedParser
{
    private int $pos = 0;

    private int $length;

    public function __construct(private readonly string $script, private readonly bool $extended)
    {
        $this->length = strlen($script);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parse(): array
    {
        $commands = [];
        $open = [];
        while (true) {
            $this->skip(" \t\n;");
            if ($this->pos >= $this->length) {
                break;
            }
            if ($this->script[$this->pos] === '#') {
                $this->skipToEol();

                continue;
            }

            $a1 = $this->address();
            $a2 = null;
            if ($a1 !== null && $this->peek() === ',') {
                $this->pos++;
                $this->skip(' ');
                $a2 = $this->address(true) ?? throw $this->error('expected an address after ","');
            }
            $this->skip(' ');
            $negate = false;
            while ($this->peek() === '!') {
                $negate = true;
                $this->pos++;
                $this->skip(' ');
            }
            $cmd = $this->peek();
            if ($cmd === null) {
                throw $this->error('missing command');
            }
            $this->pos++;
            $command = ['a1' => $a1, 'a2' => $a2, 'negate' => $negate, 'cmd' => $cmd];

            switch ($cmd) {
                case '{':
                    $open[] = count($commands);
                    $command['end'] = -1;
                    break;
                case '}':
                    if ($open === []) {
                        throw $this->error('unexpected }');
                    }
                    $commands[array_pop($open)]['end'] = count($commands);
                    break;
                case 's':
                    $command += $this->substitution();
                    break;
                case 'y':
                    $command['map'] = $this->transliteration();
                    break;
                case 'a':
                case 'i':
                case 'c':
                    $command['text'] = $this->text();
                    break;
                case 'q':
                case 'Q':
                    $this->skip(' ');
                    while (($c = $this->peek()) !== null && ctype_digit($c)) {
                        $this->pos++;
                    }
                    break;
                case 'p': case 'P': case 'd': case 'D': case '=': case 'n': case 'N':
                case 'h': case 'H': case 'g': case 'G': case 'x':
                    break;
                case 'b': case 't': case 'T': case ':':
                    throw VfsError::unsupported("sed branching ({$cmd}) is not supported in VFS sed.", ['suggestion' => 'Use addresses and ranges, several sed -e scripts, or Code Mode (code_exec) for control flow.']);
                case 'r': case 'R': case 'w': case 'W':
                    throw VfsError::unsupported("sed file command ({$cmd}) is not supported in VFS sed.", ['suggestion' => 'Read with cat and write with a shell redirect or sed -i instead.']);
                case 'e': case 'l': case 'z': case 'F':
                    throw VfsError::unsupported("sed command {$cmd} is not supported in VFS sed.", ['supported_commands' => ['s', 'y', 'p', 'P', 'd', 'D', 'q', 'Q', '=', 'a', 'i', 'c', 'n', 'N', 'h', 'H', 'g', 'G', 'x', '{', '}']]);
                default:
                    throw $this->error("unknown command \"{$cmd}\"");
            }
            $commands[] = $command;
            $this->endOfCommand($cmd);
        }
        if ($open !== []) {
            throw $this->error('unmatched {');
        }

        return $commands;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function address(bool $second = false): ?array
    {
        $c = $this->peek();
        if ($c === null) {
            return null;
        }
        if ($c === '$') {
            $this->pos++;

            return ['type' => 'last'];
        }
        if ($second && $c === '+') {
            $this->pos++;

            return ['type' => 'plus', 'n' => $this->number()];
        }
        if (ctype_digit($c)) {
            $n = $this->number();
            if ($this->peek() === '~') {
                $this->pos++;

                return ['type' => 'step', 'first' => $n, 'step' => $this->number()];
            }

            return ['type' => 'line', 'n' => $n];
        }
        if ($c === '/' || $c === '\\') {
            if ($c === '\\') {
                $this->pos++;
            }
            $delimiter = $this->script[$this->pos] ?? throw $this->error('unterminated address regex');
            $this->pos++;
            $body = $this->delimited($delimiter, 'address regex');
            $flags = '';
            while (in_array($this->peek(), ['I', 'M'], true)) {
                $flags .= $this->peek() === 'I' ? 'i' : 'm';
                $this->pos++;
            }

            return ['type' => 'regex', 're' => $this->compile($body, $flags, $delimiter)];
        }

        return null;
    }

    private function number(): int
    {
        $start = $this->pos;
        while (($c = $this->peek()) !== null && ctype_digit($c)) {
            $this->pos++;
        }
        if ($start === $this->pos) {
            throw $this->error('expected a number');
        }

        return (int) substr($this->script, $start, $this->pos - $start);
    }

    /**
     * @return array<string, mixed>
     */
    private function substitution(): array
    {
        $delimiter = $this->script[$this->pos] ?? throw $this->error('unterminated s command');
        if ($delimiter === "\n" || $delimiter === '\\') {
            throw $this->error('invalid s delimiter');
        }
        $this->pos++;
        $pattern = $this->delimited($delimiter, 's pattern');
        $replacement = $this->delimited($delimiter, 's replacement', true);

        $global = false;
        $occurrence = 1;
        $print = false;
        $flags = '';
        while (($c = $this->peek()) !== null && ! in_array($c, [';', "\n", '}', ' ', "\t", '#'], true)) {
            if ($c === 'g') {
                $global = true;
            } elseif ($c === 'p') {
                $print = true;
            } elseif ($c === 'i' || $c === 'I') {
                $flags .= 'i';
            } elseif ($c === 'm' || $c === 'M') {
                $flags .= 'm';
            } elseif (ctype_digit($c)) {
                $occurrence = $this->number();

                continue;
            } elseif ($c === 'w' || $c === 'e') {
                throw VfsError::unsupported("sed s///{$c} is not supported in VFS sed.", ['suggestion' => 'Redirect sed output or use sed -i instead.']);
            } else {
                throw $this->error("unknown option to s: \"{$c}\"");
            }
            $this->pos++;
        }

        return [
            're' => $this->compile($pattern, $flags, $delimiter),
            'replacement' => $this->replacementParts($replacement),
            'global' => $global,
            'occurrence' => max(1, $occurrence),
            'print' => $print,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function transliteration(): array
    {
        $delimiter = $this->script[$this->pos] ?? throw $this->error('unterminated y command');
        $this->pos++;
        $from = $this->unescapeText($this->delimited($delimiter, 'y source'), $delimiter);
        $to = $this->unescapeText($this->delimited($delimiter, 'y target'), $delimiter);
        $fromChars = mb_str_split($from);
        $toChars = mb_str_split($to);
        if (count($fromChars) !== count($toChars)) {
            throw $this->error('y strings have different lengths');
        }

        return array_combine($fromChars, $toChars);
    }

    /**
     * Text argument of a/i/c: GNU one-liner `a text` (to end of line) or the
     * classic `a\` followed by lines ending in `\` for continuation.
     */
    private function text(): string
    {
        $this->skip(' ');
        if ($this->peek() === '\\') {
            $this->pos++;
            if ($this->peek() === "\n") {
                $this->pos++;
            }
        }
        $text = '';
        while ($this->pos < $this->length) {
            $c = $this->script[$this->pos];
            if ($c === "\n") {
                break;
            }
            if ($c === '\\') {
                $next = $this->script[$this->pos + 1] ?? '';
                if ($next === "\n") {
                    $text .= "\n";
                    $this->pos += 2;

                    continue;
                }
                $text .= match ($next) {
                    'n' => "\n",
                    't' => "\t",
                    default => $next,
                };
                $this->pos += 2;

                continue;
            }
            $text .= $c;
            $this->pos++;
        }

        return $text;
    }

    /**
     * Read up to an unescaped delimiter. `\delimiter` becomes the delimiter
     * itself; other escapes are kept for the regex/replacement translators.
     */
    private function delimited(string $delimiter, string $what, bool $keepNewlineEscapes = false): string
    {
        $out = '';
        while ($this->pos < $this->length) {
            $c = $this->script[$this->pos];
            if ($c === '\\') {
                $next = $this->script[$this->pos + 1] ?? '';
                if ($next === $delimiter) {
                    $out .= $delimiter === '&' ? '\\&' : $delimiter;
                    $this->pos += 2;

                    continue;
                }
                if ($next === "\n") {
                    $out .= "\n";
                    $this->pos += 2;

                    continue;
                }
                $out .= $c.$next;
                $this->pos += 2;

                continue;
            }
            if ($c === $delimiter) {
                $this->pos++;

                return $out;
            }
            if ($c === "\n" && ! $keepNewlineEscapes) {
                break;
            }
            $out .= $c;
            $this->pos++;
        }

        throw $this->error("unterminated {$what}");
    }

    /**
     * Translate a sed regex (BRE, or ERE with -E) into a PCRE pattern.
     */
    private function compile(string $body, string $flags, string $delimiter): string
    {
        if ($body === '') {
            throw VfsError::unsupported('sed empty regex (reuse of the last regex) is not supported in VFS sed.', ['hint' => 'Repeat the pattern explicitly.']);
        }
        $pcre = '';
        $length = strlen($body);
        $atStart = true;
        for ($i = 0; $i < $length; $i++) {
            $c = $body[$i];
            if ($c === '[') {
                // Bracket expressions are copied verbatim (POSIX classes are PCRE-compatible).
                $end = $i + 1;
                if (($body[$end] ?? '') === '^') {
                    $end++;
                }
                if (($body[$end] ?? '') === ']') {
                    $end++;
                }
                while ($end < $length && $body[$end] !== ']') {
                    if ($body[$end] === '[' && in_array($body[$end + 1] ?? '', [':', '.', '='], true)) {
                        $close = strpos($body, $body[$end + 1].']', $end + 2);
                        $end = $close === false ? $end + 1 : $close + 2;

                        continue;
                    }
                    $end++;
                }
                if ($end >= $length) {
                    throw $this->error('unterminated [ in regex');
                }
                $class = substr($body, $i, $end - $i + 1);
                $pcre .= str_replace(['~', '\\'], ['\\~', '\\\\'], $class);
                $i = $end;
                $atStart = false;

                continue;
            }
            if ($c === '\\') {
                $next = $body[$i + 1] ?? '';
                $i++;
                if (! $this->extended && in_array($next, ['(', ')', '{', '}', '+', '?', '|'], true)) {
                    $pcre .= $next;
                } elseif ($this->extended && in_array($next, ['(', ')', '{', '}', '+', '?', '|', '.', '*', '[', ']', '^', '$', '\\', '/'], true)) {
                    $pcre .= '\\'.$next;
                } elseif ($next === '<' || $next === '>') {
                    $pcre .= '\\b';
                } elseif ($next === '`') {
                    $pcre .= '\\A';
                } elseif ($next === "'") {
                    $pcre .= '\\z';
                } elseif ($next === '~') {
                    $pcre .= '\\~';
                } elseif (in_array($next, ['n', 't', 'w', 'W', 's', 'S', 'b', 'B', '.', '*', '[', ']', '^', '$', '\\', '/', '&'], true) || ctype_digit($next)) {
                    $pcre .= '\\'.$next;
                } elseif ($next === '') {
                    throw $this->error('trailing backslash in regex');
                } else {
                    $pcre .= preg_quote($next, '~');
                }
                $atStart = false;

                continue;
            }
            if (! $this->extended && in_array($c, ['(', ')', '{', '}', '+', '?', '|'], true)) {
                $pcre .= '\\'.$c;
            } elseif ($c === '*' && $atStart) {
                $pcre .= '\\*';
            } elseif ($c === '~') {
                $pcre .= '\\~';
            } else {
                $pcre .= $c;
            }
            $atStart = $c === '^' && $pcre === '^';
        }

        $regex = '~'.$pcre.'~u'.$flags;
        if (@preg_match($regex, '') === false) {
            $regex = '~'.$pcre.'~'.$flags;
            if (@preg_match($regex, '') === false) {
                throw VfsError::invalid("Invalid sed regular expression: {$body}", ['hint' => $this->extended ? 'Check the extended regex syntax.' : 'Basic regex: use \\( \\) for groups and \\+ \\? for repetition, or pass -E for extended syntax.']);
            }
        }

        return $regex;
    }

    /**
     * @return list<string|int|array{case: string}>
     */
    private function replacementParts(string $replacement): array
    {
        $parts = [];
        $literal = '';
        $length = strlen($replacement);
        for ($i = 0; $i < $length; $i++) {
            $c = $replacement[$i];
            if ($c === '&') {
                $parts[] = $literal;
                $parts[] = 0;
                $literal = '';

                continue;
            }
            if ($c === '\\' && $i + 1 < $length) {
                $next = $replacement[++$i];
                if (ctype_digit($next)) {
                    $parts[] = $literal;
                    $parts[] = (int) $next;
                    $literal = '';
                } elseif (in_array($next, ['U', 'L', 'u', 'l', 'E'], true)) {
                    $parts[] = $literal;
                    $parts[] = ['case' => $next];
                    $literal = '';
                } else {
                    $literal .= match ($next) {
                        'n' => "\n",
                        't' => "\t",
                        default => $next,
                    };
                }

                continue;
            }
            $literal .= $c;
        }
        $parts[] = $literal;

        return array_values(array_filter($parts, fn (mixed $part): bool => $part !== ''));
    }

    private function unescapeText(string $text, string $delimiter): string
    {
        return strtr($text, ['\\n' => "\n", '\\t' => "\t", '\\\\' => '\\']);
    }

    private function endOfCommand(string $cmd): void
    {
        $this->skip(" \t");
        $c = $this->peek();
        if ($c === null || $c === ';' || $c === "\n" || $c === '}' || $c === '#') {
            return;
        }
        if ($cmd === '{') {
            return;
        }
        throw $this->error("extra characters after command {$cmd}");
    }

    private function peek(): ?string
    {
        return $this->script[$this->pos] ?? null;
    }

    private function skip(string $chars): void
    {
        while ($this->pos < $this->length && str_contains($chars, $this->script[$this->pos])) {
            $this->pos++;
        }
    }

    private function skipToEol(): void
    {
        while ($this->pos < $this->length && $this->script[$this->pos] !== "\n") {
            $this->pos++;
        }
    }

    private function error(string $detail): VfsError
    {
        return VfsError::invalid("sed: {$detail} (at character ".($this->pos + 1).').', [
            'hint' => "Example: sed -n '10,20p' file, sed 's/old/new/g' file, sed -i 's/old/new/' /files/x.md",
        ]);
    }
}
