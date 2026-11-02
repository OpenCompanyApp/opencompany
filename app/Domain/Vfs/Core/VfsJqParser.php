<?php

namespace App\Domain\Vfs\Core;

/**
 * Lexer and recursive-descent parser for VfsJqQuery programs.
 *
 * Precedence, lowest first: `|`, `,`, `//`, `or`, `and`, comparisons,
 * `+ -`, `* / %`, unary `-`, postfix (`.a`, `[e]`, `[]`, `?`).
 *
 * @internal used only by VfsJqQuery
 */
final class VfsJqParser
{
    /** Built-in functions VfsJqQuery implements, for error contexts and docs. */
    public const BUILTINS = [
        'empty', 'not', 'length', 'utf8bytelength', 'keys', 'keys_unsorted', 'values', 'has', 'in', 'map', 'map_values', 'select',
        'recurse', 'add', 'any', 'all', 'range', 'floor', 'ceil', 'round', 'abs', 'sqrt', 'tostring', 'tonumber', 'type',
        'ascii_downcase', 'ascii_upcase', 'ltrimstr', 'rtrimstr', 'trim', 'ltrim', 'rtrim', 'startswith', 'endswith', 'split', 'join',
        'test', 'sub', 'gsub', 'contains', 'inside', 'flatten', 'reverse', 'sort', 'sort_by', 'group_by', 'unique', 'unique_by',
        'min', 'max', 'min_by', 'max_by', 'first', 'last', 'nth', 'limit', 'to_entries', 'from_entries', 'with_entries',
        'tojson', 'fromjson', 'error', 'numbers', 'strings', 'booleans', 'nulls', 'arrays', 'objects', 'iterables', 'scalars',
    ];

    private const UNSUPPORTED_KEYWORDS = ['as', 'def', 'reduce', 'foreach', 'label', 'import', 'include', 'catch'];

    /** @var list<array{t: string, v: mixed}> */
    private array $tokens = [];

    private int $i = 0;

    public function __construct(private readonly string $source) {}

    /**
     * @return array<string, mixed>
     */
    public function parse(): array
    {
        $this->tokens = $this->lex($this->source);
        $this->i = 0;
        if ($this->at('eof')) {
            return ['k' => 'identity'];
        }
        $ast = $this->pipe();
        // Read the token directly: the cursor moved inside pipe().
        if ($this->tokens[$this->i]['t'] !== 'eof') {
            throw $this->error('unexpected '.$this->describe());
        }

        return $ast;
    }

    /**
     * @return list<array{t: string, v: mixed}>
     */
    private function lex(string $s): array
    {
        $tokens = [];
        $n = strlen($s);
        $i = 0;
        while ($i < $n) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;

                continue;
            }
            if ($c === '#') {
                while ($i < $n && $s[$i] !== "\n") {
                    $i++;
                }

                continue;
            }
            if ($c === '"') {
                [$parts, $i] = $this->lexString($s, $i + 1);
                $tokens[] = ['t' => 'str', 'v' => $parts];

                continue;
            }
            if ($c === "'") {
                // VFS extension: single-quoted literals (no interpolation),
                // because shell double quotes around a program force them.
                $literal = '';
                $i++;
                while ($i < $n && $s[$i] !== "'") {
                    if ($s[$i] === '\\' && in_array($s[$i + 1] ?? '', ["'", '\\'], true)) {
                        $i++;
                    }
                    $literal .= $s[$i++];
                }
                if ($i >= $n) {
                    throw $this->error('unterminated string');
                }
                $i++;
                $tokens[] = ['t' => 'str', 'v' => [$literal]];

                continue;
            }
            if ($c === '.' && ($s[$i + 1] ?? '') === '.') {
                $tokens[] = ['t' => '..', 'v' => '..'];
                $i += 2;

                continue;
            }
            if ($c === '.' && preg_match('/\G\.([A-Za-z_][A-Za-z0-9_]*)/', $s, $m, 0, $i) === 1) {
                $tokens[] = ['t' => 'field', 'v' => $m[1]];
                $i += strlen($m[0]);

                continue;
            }
            if (ctype_digit($c) || ($c === '.' && ctype_digit($s[$i + 1] ?? ''))) {
                preg_match('/\G(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $s, $m, 0, $i);
                $tokens[] = ['t' => 'num', 'v' => str_contains($m[0], '.') || stripos($m[0], 'e') !== false ? (float) $m[0] : (int) $m[0]];
                $i += strlen($m[0]);

                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*(?:::[A-Za-z_][A-Za-z0-9_]*)*/', $s, $m, 0, $i);
                $tokens[] = ['t' => 'ident', 'v' => $m[0]];
                $i += strlen($m[0]);

                continue;
            }
            if ($c === '@' && preg_match('/\G@([A-Za-z0-9_]+)/', $s, $m, 0, $i) === 1) {
                $tokens[] = ['t' => 'format', 'v' => $m[1]];
                $i += strlen($m[0]);

                continue;
            }
            if ($c === '$') {
                throw VfsError::unsupported('jq variables ($name, --arg) are not supported in VFS jq.', ['hint' => 'Inline the literal value, or use Code Mode (code_exec).']);
            }
            foreach (['//=', '|=', '+=', '-=', '*=', '/=', '%=', '==', '!=', '<=', '>=', '//', '?//'] as $op) {
                if (substr($s, $i, strlen($op)) === $op) {
                    $tokens[] = ['t' => $op, 'v' => $op];
                    $i += strlen($op);

                    continue 2;
                }
            }
            if (str_contains('.|,()[]{}:;?+-*/%<>=', $c)) {
                $tokens[] = ['t' => $c, 'v' => $c];
                $i++;

                continue;
            }
            throw $this->error("unexpected character \"{$c}\"");
        }
        $tokens[] = ['t' => 'eof', 'v' => null];

        return $tokens;
    }

    /**
     * Lex a string body starting after the opening quote; `\(expr)` becomes a
     * parsed interpolation part.
     *
     * @return array{0: list<string|array<string, mixed>>, 1: int}
     */
    private function lexString(string $s, int $i): array
    {
        $parts = [];
        $literal = '';
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if ($c === '"') {
                if ($literal !== '' || $parts === []) {
                    $parts[] = $literal;
                }

                return [$parts, $i + 1];
            }
            if ($c === '\\') {
                $e = $s[$i + 1] ?? '';
                if ($e === '(') {
                    $depth = 1;
                    $start = $i + 2;
                    $j = $start;
                    $inString = false;
                    while ($j < $n && $depth > 0) {
                        if ($inString) {
                            if ($s[$j] === '\\') {
                                $j++;
                            } elseif ($s[$j] === '"') {
                                $inString = false;
                            }
                        } elseif ($s[$j] === '"') {
                            $inString = true;
                        } elseif ($s[$j] === '(') {
                            $depth++;
                        } elseif ($s[$j] === ')') {
                            $depth--;
                        }
                        $j++;
                    }
                    if ($depth !== 0) {
                        throw $this->error('unterminated \\( interpolation');
                    }
                    if ($literal !== '') {
                        $parts[] = $literal;
                        $literal = '';
                    }
                    $parts[] = (new self(substr($s, $start, $j - 1 - $start)))->parse();
                    $i = $j;

                    continue;
                }
                if ($e === 'u' && preg_match('/\G\\\\u([0-9a-fA-F]{4})/', $s, $m, 0, $i) === 1) {
                    $literal .= mb_chr((int) hexdec($m[1])) ?: '';
                    $i += 6;

                    continue;
                }
                $literal .= match ($e) {
                    'n' => "\n", 't' => "\t", 'r' => "\r", 'b' => "\x08", 'f' => "\f", '"' => '"', '\\' => '\\', '/' => '/',
                    default => throw $this->error("invalid escape \\{$e} in string"),
                };
                $i += 2;

                continue;
            }
            $literal .= $c;
            $i++;
        }

        throw $this->error('unterminated string');
    }

    /**
     * @return array<string, mixed>
     */
    private function pipe(): array
    {
        $left = $this->comma();
        if ($this->at('|')) {
            $this->i++;

            return ['k' => 'pipe', 'l' => $left, 'r' => $this->pipe()];
        }

        return $left;
    }

    /**
     * Object values: a pipe without top-level commas.
     *
     * @return array<string, mixed>
     */
    private function pipeNoComma(): array
    {
        $left = $this->alternative();
        if ($this->at('|')) {
            $this->i++;

            return ['k' => 'pipe', 'l' => $left, 'r' => $this->pipeNoComma()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function comma(): array
    {
        $left = $this->alternative();
        while ($this->at(',')) {
            $this->i++;
            $left = ['k' => 'comma', 'l' => $left, 'r' => $this->alternative()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function alternative(): array
    {
        $left = $this->orExpr();
        if (in_array($this->tokens[$this->i]['t'], ['=', '|=', '+=', '-=', '*=', '/=', '%=', '//='], true)) {
            throw VfsError::unsupported('jq assignment operators (=, |=, +=, ...) are not supported in VFS jq.', ['hint' => 'Build the new value with object construction ({a: .a, b: 1}) or use Code Mode (code_exec) for JSON rewrites.']);
        }
        if ($this->at('//')) {
            $this->i++;

            return ['k' => 'alt', 'l' => $left, 'r' => $this->alternative()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function orExpr(): array
    {
        $left = $this->andExpr();
        while ($this->atIdent('or')) {
            $this->i++;
            $left = ['k' => 'or', 'l' => $left, 'r' => $this->andExpr()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function andExpr(): array
    {
        $left = $this->comparison();
        while ($this->atIdent('and')) {
            $this->i++;
            $left = ['k' => 'and', 'l' => $left, 'r' => $this->comparison()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function comparison(): array
    {
        $left = $this->additive();
        $t = $this->tokens[$this->i]['t'];
        if (in_array($t, ['==', '!=', '<', '<=', '>', '>='], true)) {
            $this->i++;

            return ['k' => 'binop', 'op' => $t, 'l' => $left, 'r' => $this->additive()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function additive(): array
    {
        $left = $this->multiplicative();
        while ($this->at('+') || $this->at('-')) {
            $op = $this->tokens[$this->i++]['t'];
            $left = ['k' => 'binop', 'op' => $op, 'l' => $left, 'r' => $this->multiplicative()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function multiplicative(): array
    {
        $left = $this->unary();
        while ($this->at('*') || $this->at('/') || $this->at('%')) {
            $op = $this->tokens[$this->i++]['t'];
            $left = ['k' => 'binop', 'op' => $op, 'l' => $left, 'r' => $this->unary()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function unary(): array
    {
        if ($this->at('-')) {
            $this->i++;

            return ['k' => 'neg', 'e' => $this->unary()];
        }

        return $this->postfix($this->primary());
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    private function postfix(array $target): array
    {
        while (true) {
            if ($this->at('field')) {
                $target = ['k' => 'field', 'target' => $target, 'name' => $this->tokens[$this->i++]['v']];

                continue;
            }
            if ($this->at('.') && ($this->peek(1) === 'str' || $this->peek(1) === '[')) {
                $this->i++;

                continue;
            }
            if ($this->at('str') && $this->tokens[$this->i - 1]['t'] === '.') {
                $parts = $this->tokens[$this->i++]['v'];
                $target = ['k' => 'index', 'target' => $target, 'index' => ['k' => 'string', 'parts' => $parts]];

                continue;
            }
            if ($this->at('[')) {
                $this->i++;
                if ($this->at(']')) {
                    $this->i++;
                    $target = ['k' => 'iterate', 'target' => $target];

                    continue;
                }
                $from = $this->at(':') ? null : $this->pipe();
                if ($this->at(':')) {
                    $this->i++;
                    $to = $this->at(']') ? null : $this->pipe();
                    $this->expect(']');
                    $target = ['k' => 'slice', 'target' => $target, 'from' => $from, 'to' => $to];

                    continue;
                }
                $this->expect(']');
                $target = ['k' => 'index', 'target' => $target, 'index' => $from];

                continue;
            }
            if ($this->at('?')) {
                $this->i++;
                $target = ['k' => 'try', 'e' => $target];

                continue;
            }

            return $target;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function primary(): array
    {
        $token = $this->tokens[$this->i];
        switch ($token['t']) {
            case '.':
                $this->i++;
                if ($this->at('str')) {
                    $parts = $this->tokens[$this->i++]['v'];

                    return ['k' => 'index', 'target' => ['k' => 'identity'], 'index' => ['k' => 'string', 'parts' => $parts]];
                }

                return ['k' => 'identity'];
            case 'field':
                $this->i++;

                return ['k' => 'field', 'target' => ['k' => 'identity'], 'name' => $token['v']];
            case '..':
                $this->i++;

                return ['k' => 'recurse'];
            case 'num':
                $this->i++;

                return ['k' => 'literal', 'v' => $token['v']];
            case 'str':
                $this->i++;
                $parts = $token['v'];

                return count($parts) === 1 && is_string($parts[0]) ? ['k' => 'literal', 'v' => $parts[0]] : ['k' => 'string', 'parts' => $parts];
            case 'format':
                $this->i++;
                if ($this->at('str')) {
                    throw VfsError::unsupported('jq @format "string" interpolation is not supported in VFS jq.', ['hint' => 'Use "\\(...)" interpolation and pipe the value through @csv/@tsv separately.']);
                }

                return ['k' => 'format', 'name' => $token['v']];
            case '(':
                $this->i++;
                $inner = $this->pipe();
                $this->expect(')');

                return $inner;
            case '[':
                $this->i++;
                if ($this->at(']')) {
                    $this->i++;

                    return ['k' => 'array', 'e' => null];
                }
                $inner = $this->pipe();
                $this->expect(']');

                return ['k' => 'array', 'e' => $inner];
            case '{':
                return $this->object();
            case 'ident':
                return $this->identifier();
        }

        throw $this->error('unexpected '.$this->describe());
    }

    /**
     * @return array<string, mixed>
     */
    private function identifier(): array
    {
        $name = $this->tokens[$this->i++]['v'];
        if (in_array($name, self::UNSUPPORTED_KEYWORDS, true)) {
            throw VfsError::unsupported("jq {$name} is not supported in VFS jq.", ['hint' => 'Use map/select/sort_by/group_by/add for aggregation, or Code Mode (code_exec).']);
        }
        if ($name === 'true' || $name === 'false') {
            return ['k' => 'literal', 'v' => $name === 'true'];
        }
        if ($name === 'null') {
            return ['k' => 'literal', 'v' => null];
        }
        if ($name === 'if') {
            return $this->conditional();
        }
        if ($name === 'try') {
            $body = $this->postfix($this->primary());
            if ($this->atIdent('catch')) {
                throw VfsError::unsupported('jq try ... catch is not supported in VFS jq; use try EXPR or EXPR? to drop errors.');
            }

            return ['k' => 'try', 'e' => $body];
        }
        $args = [];
        if ($this->at('(')) {
            $this->i++;
            $args[] = $this->pipe();
            while ($this->at(';')) {
                $this->i++;
                $args[] = $this->pipe();
            }
            $this->expect(')');
        }

        return ['k' => 'call', 'name' => $name, 'args' => $args];
    }

    /**
     * @return array<string, mixed>
     */
    private function conditional(): array
    {
        $cond = $this->pipe();
        $this->expectIdent('then');
        $then = $this->pipe();
        if ($this->atIdent('elif')) {
            $this->i++;

            return ['k' => 'if', 'cond' => $cond, 'then' => $then, 'else' => $this->conditional()];
        }
        $else = null;
        if ($this->atIdent('else')) {
            $this->i++;
            $else = $this->pipe();
        }
        $this->expectIdent('end');

        return ['k' => 'if', 'cond' => $cond, 'then' => $then, 'else' => $else];
    }

    /**
     * @return array<string, mixed>
     */
    private function object(): array
    {
        $this->expect('{');
        $entries = [];
        while (! $this->at('}')) {
            $token = $this->tokens[$this->i];
            if ($token['t'] === 'ident' || $token['t'] === 'field') {
                $this->i++;
                $key = $token['v'];
            } elseif ($token['t'] === 'str') {
                $this->i++;
                $parts = $token['v'];
                $key = count($parts) === 1 && is_string($parts[0]) ? $parts[0] : ['k' => 'string', 'parts' => $parts];
            } elseif ($token['t'] === '(') {
                $this->i++;
                $key = $this->pipe();
                $this->expect(')');
            } else {
                throw $this->error('expected an object key');
            }
            $value = null;
            if ($this->at(':')) {
                $this->i++;
                $value = $this->pipeNoComma();
            } elseif (! is_string($key)) {
                throw $this->error('computed object keys need a value ({(.k): .v})');
            }
            $entries[] = ['key' => $key, 'value' => $value];
            if ($this->at(',')) {
                $this->i++;

                continue;
            }
            if (! $this->at('}')) {
                throw $this->error('expected , or } in object');
            }
        }
        $this->i++;

        return ['k' => 'object', 'entries' => $entries];
    }

    private function peek(int $offset): string
    {
        return $this->tokens[$this->i + $offset]['t'] ?? 'eof';
    }

    private function at(string $type): bool
    {
        return $this->tokens[$this->i]['t'] === $type;
    }

    private function atIdent(string $word): bool
    {
        return $this->tokens[$this->i]['t'] === 'ident' && $this->tokens[$this->i]['v'] === $word;
    }

    private function expect(string $type): void
    {
        if (! $this->at($type)) {
            throw $this->error("expected \"{$type}\" but found ".$this->describe());
        }
        $this->i++;
    }

    private function expectIdent(string $word): void
    {
        if (! $this->atIdent($word)) {
            throw $this->error("expected \"{$word}\" but found ".$this->describe());
        }
        $this->i++;
    }

    private function describe(): string
    {
        $token = $this->tokens[$this->i];
        if ($token['t'] === 'eof') {
            return 'end of program';
        }

        return '"'.(is_scalar($token['v']) ? $token['v'] : $token['t']).'"';
    }

    private function error(string $detail): VfsError
    {
        return VfsError::invalid("jq: syntax error: {$detail}.", [
            'hint' => 'Examples: jq \'.items[] | select(.status == "open") | .name\', jq -r \'.[] | [.id, .name] | @csv\'',
        ]);
    }
}
