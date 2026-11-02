<?php

namespace App\Domain\Vfs\Core;

/**
 * Lexer and recursive-descent parser for the VFS awk subset.
 *
 * Produces an array AST consumed by VfsAwkProgram. Grammar follows POSIX awk
 * precedence (assignment < ?: < || < && < in < ~ < comparison < concatenation
 * < additive < multiplicative < unary < ^ < ++/-- < $ < grouping). Regex
 * literals are recognized where an operand is expected, so `a / b` divides
 * and `/x/ {...}` matches.
 *
 * Rejected with `unsupported`: getline, system(), user functions, and output
 * redirection (`print > "f"`, `print | "cmd"`) — they would reach storage or
 * processes outside the governed VFS paths.
 *
 * @internal used only by VfsAwkProgram
 */
final class VfsAwkParser
{
    /** @var list<array{t: string, v: mixed, call?: bool}> */
    private array $tokens = [];

    private int $i = 0;

    private const KEYWORDS = ['BEGIN', 'END', 'if', 'else', 'while', 'for', 'do', 'break', 'continue', 'next', 'nextfile', 'exit', 'print', 'printf', 'delete', 'in', 'getline', 'function', 'func', 'return'];

    private const BUILTINS = ['length', 'substr', 'index', 'split', 'sub', 'gsub', 'match', 'sprintf', 'tolower', 'toupper', 'int', 'sqrt', 'exp', 'log', 'sin', 'cos', 'atan2', 'rand', 'srand', 'system', 'close', 'fflush'];

    public function __construct(private readonly string $source) {}

    /**
     * @return array{begin: list<array<string, mixed>>, end: list<array<string, mixed>>, rules: list<array<string, mixed>>}
     */
    public function parse(): array
    {
        $this->tokens = $this->lex($this->source);
        $this->i = 0;
        $program = ['begin' => [], 'end' => [], 'rules' => []];

        $this->skipTerminators();
        while (! $this->at('eof')) {
            if ($this->atKeyword('BEGIN')) {
                $this->i++;
                $program['begin'][] = $this->block();
            } elseif ($this->atKeyword('END')) {
                $this->i++;
                $program['end'][] = $this->block();
            } elseif ($this->atKeyword('function') || $this->atKeyword('func')) {
                throw VfsError::unsupported('awk user-defined functions are not supported in VFS awk.', ['suggestion' => 'Inline the logic, or use Code Mode (code_exec).']);
            } else {
                $pattern = null;
                $pattern2 = null;
                if (! $this->at('{')) {
                    $pattern = $this->expression();
                    if ($this->at(',')) {
                        $this->i++;
                        $this->skipNewlines();
                        $pattern2 = $this->expression();
                    }
                }
                $action = $this->at('{') ? $this->block() : null;
                $program['rules'][] = ['pattern' => $pattern, 'pattern2' => $pattern2, 'action' => $action];
            }
            $this->skipTerminators();
        }

        return $program;
    }

    // ------------------------------------------------------------- lexer

    /**
     * @return list<array{t: string, v: mixed, call?: bool}>
     */
    private function lex(string $s): array
    {
        $tokens = [];
        $n = strlen($s);
        $i = 0;
        while ($i < $n) {
            $c = $s[$i];
            if ($c === ' ' || $c === "\t" || $c === "\r") {
                $i++;

                continue;
            }
            if ($c === '\\' && ($s[$i + 1] ?? '') === "\n") {
                $i += 2;

                continue;
            }
            if ($c === '#') {
                while ($i < $n && $s[$i] !== "\n") {
                    $i++;
                }

                continue;
            }
            if ($c === "\n") {
                $tokens[] = ['t' => 'nl', 'v' => null];
                $i++;

                continue;
            }
            if ($c === '"') {
                $i++;
                $str = '';
                while ($i < $n && $s[$i] !== '"') {
                    if ($s[$i] === '\\' && $i + 1 < $n) {
                        $e = $s[$i + 1];
                        $str .= match ($e) {
                            'n' => "\n", 't' => "\t", 'r' => "\r", '\\' => '\\', '"' => '"', '/' => '/', 'a' => "\x07", 'b' => "\x08", 'f' => "\f", 'v' => "\v",
                            default => '\\'.$e,
                        };
                        $i += 2;

                        continue;
                    }
                    $str .= $s[$i++];
                }
                if ($i >= $n) {
                    throw $this->error('unterminated string');
                }
                $i++;
                $tokens[] = ['t' => 'str', 'v' => $str];

                continue;
            }
            if ($c === '/' && $this->regexAllowed($tokens)) {
                $i++;
                $re = '';
                $inClass = false;
                while ($i < $n && ($s[$i] !== '/' || $inClass)) {
                    if ($s[$i] === '\\' && $i + 1 < $n) {
                        $re .= $s[$i + 1] === '/' ? '/' : $s[$i].$s[$i + 1];
                        $i += 2;

                        continue;
                    }
                    if ($s[$i] === '[') {
                        $inClass = true;
                    } elseif ($s[$i] === ']') {
                        $inClass = false;
                    } elseif ($s[$i] === "\n") {
                        throw $this->error('newline in regex');
                    }
                    $re .= $s[$i++];
                }
                if ($i >= $n) {
                    throw $this->error('unterminated regex');
                }
                $i++;
                $tokens[] = ['t' => 're', 'v' => $re];

                continue;
            }
            if (ctype_digit($c) || ($c === '.' && ctype_digit($s[$i + 1] ?? ''))) {
                if (preg_match('/\G(?:0[xX][0-9a-fA-F]+|\d*\.?\d+(?:[eE][+-]?\d+)?|\d+\.)/', $s, $m, 0, $i) === 1) {
                    $i += strlen($m[0]);
                    $tokens[] = ['t' => 'num', 'v' => match (true) {
                        str_starts_with(strtolower($m[0]), '0x') => hexdec($m[0]),
                        str_contains($m[0], '.') || stripos($m[0], 'e') !== false => (float) $m[0],
                        default => (int) $m[0],
                    }];

                    continue;
                }
            }
            if (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $i);
                $word = $m[0];
                $i += strlen($word);
                if (in_array($word, self::KEYWORDS, true)) {
                    $tokens[] = ['t' => 'kw', 'v' => $word];
                } elseif (in_array($word, self::BUILTINS, true)) {
                    $tokens[] = ['t' => 'fn', 'v' => $word];
                } else {
                    $tokens[] = ['t' => 'name', 'v' => $word, 'call' => ($s[$i] ?? '') === '('];
                }

                continue;
            }
            foreach (['&&', '||', '==', '!=', '<=', '>=', '!~', '++', '--', '+=', '-=', '*=', '/=', '%=', '^=', '**', '>>'] as $op) {
                if (substr($s, $i, strlen($op)) === $op) {
                    $tokens[] = ['t' => $op === '**' ? '^' : $op, 'v' => $op];
                    $i += strlen($op);

                    continue 2;
                }
            }
            if (str_contains('{}()[];,+-*/%^!<>|?:~=$', $c)) {
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
     * @param  list<array{t: string, v: mixed}>  $tokens
     */
    private function regexAllowed(array $tokens): bool
    {
        $last = $tokens[count($tokens) - 1] ?? null;
        if ($last === null) {
            return true;
        }

        return ! in_array($last['t'], ['num', 'str', 'name', ')', ']', '$', '++', '--', 're'], true)
            && ! ($last['t'] === 'kw' && in_array($last['v'], ['getline'], true))
            && ! ($last['t'] === 'fn' && $last['v'] === 'length');
    }

    // ------------------------------------------------------------- parser

    /**
     * @return array<string, mixed>
     */
    private function block(): array
    {
        $this->skipNewlines();
        $this->expect('{');
        $body = [];
        $this->skipTerminators();
        while (! $this->at('}')) {
            if ($this->at('eof')) {
                throw $this->error('missing }');
            }
            $body[] = $this->statement();
            $this->skipTerminators();
        }
        $this->i++;

        return ['k' => 'block', 'body' => $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function statement(): array
    {
        if ($this->at('{')) {
            return $this->block();
        }
        if ($this->at(';')) {
            $this->i++;

            return ['k' => 'block', 'body' => []];
        }
        if ($this->atKeyword('if')) {
            $this->i++;
            $this->expect('(');
            $cond = $this->expression();
            $this->expect(')');
            $this->skipNewlines();
            $then = $this->statement();
            $save = $this->i;
            $this->skipTerminators();
            if ($this->atKeyword('else')) {
                $this->i++;
                $this->skipNewlines();

                return ['k' => 'if', 'cond' => $cond, 'then' => $then, 'else' => $this->statement()];
            }
            $this->i = $save;

            return ['k' => 'if', 'cond' => $cond, 'then' => $then, 'else' => null];
        }
        if ($this->atKeyword('while')) {
            $this->i++;
            $this->expect('(');
            $cond = $this->expression();
            $this->expect(')');
            if ($this->at(';')) {
                $this->i++;

                return ['k' => 'while', 'cond' => $cond, 'body' => ['k' => 'block', 'body' => []]];
            }
            $this->skipNewlines();

            return ['k' => 'while', 'cond' => $cond, 'body' => $this->statement()];
        }
        if ($this->atKeyword('do')) {
            $this->i++;
            $this->skipNewlines();
            $body = $this->statement();
            $this->skipTerminators();
            if (! $this->atKeyword('while')) {
                throw $this->error('expected while after do body');
            }
            $this->i++;
            $this->expect('(');
            $cond = $this->expression();
            $this->expect(')');

            return ['k' => 'do', 'cond' => $cond, 'body' => $body];
        }
        if ($this->atKeyword('for')) {
            $this->i++;
            $this->expect('(');
            if ($this->at('name') && ($this->tokens[$this->i + 1]['t'] ?? '') === 'kw' && ($this->tokens[$this->i + 1]['v'] ?? '') === 'in') {
                $var = $this->tokens[$this->i]['v'];
                $this->i += 2;
                $array = $this->expectName();
                $this->expect(')');
                $this->skipNewlines();

                return ['k' => 'forin', 'var' => $var, 'array' => $array, 'body' => $this->statement()];
            }
            $init = $this->at(';') ? null : $this->simpleStatement();
            $this->expect(';');
            $this->skipNewlines();
            $cond = $this->at(';') ? null : $this->expression();
            $this->expect(';');
            $this->skipNewlines();
            $step = $this->at(')') ? null : $this->simpleStatement();
            $this->expect(')');
            if ($this->at(';')) {
                $this->i++;

                return ['k' => 'for', 'init' => $init, 'cond' => $cond, 'step' => $step, 'body' => ['k' => 'block', 'body' => []]];
            }
            $this->skipNewlines();

            return ['k' => 'for', 'init' => $init, 'cond' => $cond, 'step' => $step, 'body' => $this->statement()];
        }

        $statement = $this->simpleStatement();
        // Read the token directly: the cursor moved inside simpleStatement().
        if (! in_array($this->tokens[$this->i]['t'], [';', 'nl', '}', 'eof'], true)) {
            throw $this->error('expected ; or newline after statement');
        }

        return $statement;
    }

    /**
     * @return array<string, mixed>
     */
    private function simpleStatement(): array
    {
        if ($this->atKeyword('print') || $this->atKeyword('printf')) {
            $kind = $this->tokens[$this->i++]['v'];
            $args = [];
            $this->noGreater++;
            if (! $this->at(';') && ! $this->at('nl') && ! $this->at('}') && ! $this->at('eof') && ! $this->at('>') && ! $this->at('|') && ! $this->at('>>')) {
                if ($this->at('(')) {
                    // print (a, b) / printf("fmt", x): a parenthesized list.
                    $save = $this->i;
                    $this->i++;
                    $this->noGreater--;
                    $list = $this->expressionList(')');
                    $this->noGreater++;
                    $this->expect(')');
                    if ($this->at(';') || $this->at('nl') || $this->at('}') || $this->at('eof') || $this->at('>') || $this->at('|') || $this->at('>>')) {
                        $args = $list;
                    } else {
                        $this->i = $save;
                        $args = $this->expressionList(null);
                    }
                } else {
                    $args = $this->expressionList(null);
                }
            }
            $this->noGreater--;
            if ($this->at('>') || $this->at('>>') || $this->at('|')) {
                throw VfsError::unsupported('awk output redirection (print > file, print | cmd) is not supported in VFS awk.', ['suggestion' => 'Redirect the whole awk command instead: awk \'...\' input > /files/out.txt']);
            }
            if ($kind === 'printf' && $args === []) {
                throw $this->error('printf requires a format');
            }

            return ['k' => $kind, 'args' => $args];
        }
        if ($this->atKeyword('next') || $this->atKeyword('nextfile')) {
            $this->i++;

            return ['k' => 'next'];
        }
        if ($this->atKeyword('exit')) {
            $this->i++;
            $code = ($this->at(';') || $this->at('nl') || $this->at('}') || $this->at('eof')) ? null : $this->expression();

            return ['k' => 'exit', 'code' => $code];
        }
        if ($this->atKeyword('break') || $this->atKeyword('continue')) {
            return ['k' => $this->tokens[$this->i++]['v']];
        }
        if ($this->atKeyword('delete')) {
            $this->i++;
            $name = $this->expectName();
            if ($this->at('[')) {
                $this->i++;
                $subscript = $this->expressionList(']');
                $this->expect(']');

                return ['k' => 'delete', 'name' => $name, 'subscript' => $subscript];
            }

            return ['k' => 'delete', 'name' => $name, 'subscript' => null];
        }
        if ($this->atKeyword('getline')) {
            throw VfsError::unsupported('awk getline is not supported in VFS awk.', ['suggestion' => 'Process input line by line with patterns, or join files first.']);
        }
        if ($this->atKeyword('return')) {
            throw VfsError::unsupported('awk return is only valid in user functions, which VFS awk does not support.');
        }

        return ['k' => 'expr', 'e' => $this->expression()];
    }

    /** Depth counter: inside print arguments `>` is redirection, not comparison. */
    private int $noGreater = 0;

    /**
     * @return list<array<string, mixed>>
     */
    private function expressionList(?string $closer): array
    {
        $list = [];
        if ($closer !== null && $this->at($closer)) {
            return $list;
        }
        $list[] = $this->expression();
        while ($this->at(',')) {
            $this->i++;
            $this->skipNewlines();
            $list[] = $this->expression();
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    private function expression(): array
    {
        return $this->assignment();
    }

    /**
     * @return array<string, mixed>
     */
    private function assignment(): array
    {
        $left = $this->ternary();
        $t = $this->tokens[$this->i]['t'];
        if (in_array($t, ['=', '+=', '-=', '*=', '/=', '%=', '^='], true)) {
            if (! in_array($left['k'], ['var', 'index', 'field'], true)) {
                throw $this->error('assignment to a non-variable');
            }
            $this->i++;
            $this->skipNewlines();

            return ['k' => 'assign', 'op' => $t, 'target' => $left, 'value' => $this->assignment()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function ternary(): array
    {
        $cond = $this->orExpr();
        if ($this->at('?')) {
            $this->i++;
            $this->skipNewlines();
            $then = $this->ternary();
            $this->skipNewlines();
            $this->expect(':');
            $this->skipNewlines();

            return ['k' => 'cond', 'cond' => $cond, 'then' => $then, 'else' => $this->ternary()];
        }

        return $cond;
    }

    /**
     * @return array<string, mixed>
     */
    private function orExpr(): array
    {
        $left = $this->andExpr();
        while ($this->at('||')) {
            $this->i++;
            $this->skipNewlines();
            $left = ['k' => 'or', 'l' => $left, 'r' => $this->andExpr()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function andExpr(): array
    {
        $left = $this->inExpr();
        while ($this->at('&&')) {
            $this->i++;
            $this->skipNewlines();
            $left = ['k' => 'and', 'l' => $left, 'r' => $this->inExpr()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function inExpr(): array
    {
        $left = $this->matchExpr();
        while ($this->atKeyword('in')) {
            $this->i++;
            $left = ['k' => 'in', 'subscript' => $left['k'] === 'group' ? $left['list'] : [$left], 'array' => $this->expectName()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function matchExpr(): array
    {
        $left = $this->comparison();
        while ($this->at('~') || $this->at('!~')) {
            $negate = $this->tokens[$this->i++]['t'] === '!~';
            $left = ['k' => 'match', 'negate' => $negate, 'l' => $left, 'r' => $this->comparison()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function comparison(): array
    {
        $left = $this->concatenation();
        $t = $this->tokens[$this->i]['t'];
        if (in_array($t, ['<', '<=', '==', '!=', '>=', '>'], true) && ! ($t === '>' && $this->noGreater > 0)) {
            $this->i++;

            return ['k' => 'cmp', 'op' => $t, 'l' => $left, 'r' => $this->concatenation()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function concatenation(): array
    {
        $left = $this->additive();
        while ($this->startsOperand()) {
            $left = ['k' => 'concat', 'l' => $left, 'r' => $this->additive()];
        }

        return $left;
    }

    private function startsOperand(): bool
    {
        $token = $this->tokens[$this->i];

        // `-` is never an operand start here: `a -1` is subtraction, as in awk.
        return in_array($token['t'], ['num', 'str', 're', 'name', 'fn', '$', '(', '!', '++', '--'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function additive(): array
    {
        $left = $this->multiplicative();
        while ($this->at('+') || $this->at('-')) {
            $op = $this->tokens[$this->i++]['t'];
            $left = ['k' => 'bin', 'op' => $op, 'l' => $left, 'r' => $this->multiplicative()];
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
            $left = ['k' => 'bin', 'op' => $op, 'l' => $left, 'r' => $this->unary()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function unary(): array
    {
        if ($this->at('!')) {
            $this->i++;

            return ['k' => 'not', 'e' => $this->unary()];
        }
        if ($this->at('-') || $this->at('+')) {
            $op = $this->tokens[$this->i++]['t'];

            return ['k' => 'neg', 'op' => $op, 'e' => $this->unary()];
        }

        return $this->power();
    }

    /**
     * @return array<string, mixed>
     */
    private function power(): array
    {
        $base = $this->postfix();
        if ($this->at('^')) {
            $this->i++;

            return ['k' => 'bin', 'op' => '^', 'l' => $base, 'r' => $this->unaryPowerOperand()];
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function unaryPowerOperand(): array
    {
        if ($this->at('-') || $this->at('+')) {
            $op = $this->tokens[$this->i++]['t'];

            return ['k' => 'neg', 'op' => $op, 'e' => $this->unaryPowerOperand()];
        }

        return $this->power();
    }

    /**
     * @return array<string, mixed>
     */
    private function postfix(): array
    {
        if ($this->at('++') || $this->at('--')) {
            $op = $this->tokens[$this->i++]['t'];
            $target = $this->postfix();
            $this->assertLvalue($target);

            return ['k' => 'incr', 'op' => $op, 'prefix' => true, 'target' => $target];
        }
        $primary = $this->primary();
        if (in_array($primary['k'], ['var', 'index', 'field'], true) && in_array($this->tokens[$this->i]['t'], ['++', '--'], true)) {
            $op = $this->tokens[$this->i++]['t'];

            return ['k' => 'incr', 'op' => $op, 'prefix' => false, 'target' => $primary];
        }

        return $primary;
    }

    /**
     * @return array<string, mixed>
     */
    private function primary(): array
    {
        $token = $this->tokens[$this->i];
        switch ($token['t']) {
            case 'num':
                $this->i++;

                return ['k' => 'num', 'v' => $token['v']];
            case 'str':
                $this->i++;

                return ['k' => 'str', 'v' => $token['v']];
            case 're':
                $this->i++;

                return ['k' => 're', 'v' => $token['v']];
            case '$':
                $this->i++;
                if ($this->at('++') || $this->at('--')) {
                    $op = $this->tokens[$this->i++]['t'];
                    $target = $this->primary();

                    return ['k' => 'field', 'e' => ['k' => 'incr', 'op' => $op, 'prefix' => true, 'target' => $target]];
                }
                if ($this->at('-')) {
                    $this->i++;

                    return ['k' => 'field', 'e' => ['k' => 'neg', 'op' => '-', 'e' => $this->primary()]];
                }

                return ['k' => 'field', 'e' => $this->primary()];
            case '(':
                $this->i++;
                // Inside parentheses `>` is a comparison again, even in print.
                $saved = $this->noGreater;
                $this->noGreater = 0;
                $list = $this->expressionList(')');
                $this->noGreater = $saved;
                $this->expect(')');
                if (count($list) === 1) {
                    return ['k' => 'group', 'e' => $list[0], 'list' => $list];
                }
                if (! $this->atKeyword('in')) {
                    throw $this->error('a parenthesized list must be followed by "in"');
                }

                return ['k' => 'group', 'e' => $list[0], 'list' => $list];
            case 'fn':
                return $this->builtinCall();
            case 'name':
                $this->i++;
                if (($token['call'] ?? false) === true) {
                    throw VfsError::unsupported("awk user-defined function {$token['v']}() is not supported in VFS awk.", ['suggestion' => 'Use the built-in functions (length substr index split sub gsub match sprintf tolower toupper int).']);
                }
                if ($this->at('[')) {
                    $this->i++;
                    $subscript = $this->expressionList(']');
                    $this->expect(']');

                    return ['k' => 'index', 'name' => $token['v'], 'subscript' => $subscript];
                }

                return ['k' => 'var', 'name' => $token['v']];
            case 'kw':
                if ($token['v'] === 'getline') {
                    throw VfsError::unsupported('awk getline is not supported in VFS awk.');
                }
                // fallthrough
            default:
                throw $this->error('unexpected '.($token['v'] ?? $token['t']));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function builtinCall(): array
    {
        $name = $this->tokens[$this->i++]['v'];
        if (in_array($name, ['system', 'close', 'fflush'], true)) {
            throw VfsError::unsupported("awk {$name}() is not supported in VFS awk.", ['suggestion' => 'VFS awk cannot run commands or manage files; redirect the awk output instead.']);
        }
        $args = [];
        if ($this->at('(')) {
            $this->i++;
            $saved = $this->noGreater;
            $this->noGreater = 0;
            $args = $this->expressionList(')');
            $this->noGreater = $saved;
            $this->expect(')');
        } elseif ($name !== 'length') {
            throw $this->error("{$name} requires parentheses");
        }

        return ['k' => 'call', 'name' => $name, 'args' => $args];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function assertLvalue(array $node): void
    {
        if (! in_array($node['k'], ['var', 'index', 'field'], true)) {
            throw $this->error('++/-- requires a variable');
        }
    }

    private function expectName(): string
    {
        if (! $this->at('name')) {
            throw $this->error('expected a name');
        }

        return $this->tokens[$this->i++]['v'];
    }

    private function expect(string $type): void
    {
        if (! $this->at($type)) {
            $token = $this->tokens[$this->i];
            throw $this->error("expected \"{$type}\" but found \"".($token['v'] ?? $token['t']).'"');
        }
        $this->i++;
    }

    private function at(string $type): bool
    {
        return $this->tokens[$this->i]['t'] === $type;
    }

    private function atKeyword(string $word): bool
    {
        $token = $this->tokens[$this->i];

        return $token['t'] === 'kw' && $token['v'] === $word;
    }

    private function skipNewlines(): void
    {
        while ($this->at('nl')) {
            $this->i++;
        }
    }

    private function skipTerminators(): void
    {
        while ($this->at('nl') || $this->at(';')) {
            $this->i++;
        }
    }

    private function error(string $detail): VfsError
    {
        return VfsError::invalid("awk: syntax error: {$detail}.", [
            'hint' => "Example: awk -F, 'NR>1 {sum += \$3} END {print sum}' /files/data.csv",
        ]);
    }
}
