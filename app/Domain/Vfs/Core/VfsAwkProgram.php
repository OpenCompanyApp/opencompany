<?php

namespace App\Domain\Vfs\Core;

/**
 * Pure, bounded awk interpreter for the VFS shell (POSIX awk subset).
 *
 * Owns running a parsed program (VfsAwkParser) over in-memory input: BEGIN/END,
 * expression and /regex/ patterns, range patterns, fields ($0, $N, $NF, field
 * assignment rebuilding $0 with OFS), NR NF FNR FS OFS ORS SUBSEP RSTART
 * RLENGTH FILENAME, scalars and associative arrays, control flow, print and
 * printf (via VfsPrintf), and the common string/math built-ins.
 *
 * Does not own: reading files (the executor passes contents in) or any output
 * besides the returned string. getline, system(), user functions and output
 * redirection are rejected at parse time.
 *
 * Value model: numbers are PHP int/float, strings are PHP strings, and
 * uninitialized values are null. Comparison is numeric when both sides are
 * numbers, numeric-looking strings, or uninitialized — the usual field
 * ("strnum") behavior agents expect from `$3 > 100`; string constants that look
 * numeric therefore also compare numerically (a documented simplification).
 *
 * Bounded by MAX_STEPS evaluations so `while (1)` cannot hang a request.
 *
 * Resource invariant: steps bound time, not memory. A field index or NF
 * assignment (`NF=100000000`, `$100000000="x"`) is checked against
 * MAX_FIELDS, and every string the program builds (concatenation, sub/gsub,
 * sprintf via VfsPrintf's own caps) against the run's byte limit, BEFORE
 * the allocation happens, so `s = s s` in a loop or a huge field index
 * fails with an invalid_request VfsError instead of a PHP memory fatal.
 */
final class VfsAwkProgram
{
    private const MAX_STEPS = 5_000_000;

    private const MAX_OUTPUT_BYTES = 50_000_000;

    /** Largest field index or NF a program may set ($N = ..., NF = N). */
    public const MAX_FIELDS = 100_000;

    /** Byte limit of the run in progress for any string the program builds. */
    private int $maxBytes = PHP_INT_MAX;

    /** @var array<string, mixed> */
    private array $vars = [];

    /** @var array<string, array<string, mixed>> */
    private array $arrays = [];

    private string $record = '';

    /** @var list<string>|null Fields 1..NF (index 0 = $1); null when $0 changed and not yet split. */
    private ?array $fields = [];

    private string $out = '';

    private int $steps = 0;

    /** @var array<string, string> */
    private array $regexCache = [];

    /** @var array<int, bool> */
    private array $rangeActive = [];

    /**
     * @param  array{begin: list<array<string, mixed>>, end: list<array<string, mixed>>, rules: list<array<string, mixed>>}  $program
     */
    private function __construct(private readonly array $program) {}

    /**
     * @throws VfsError invalid_request for syntax errors, unsupported for rejected constructs
     */
    public static function parse(string $source): self
    {
        return new self((new VfsAwkParser($source))->parse());
    }

    /**
     * Run the program.
     *
     * @param  list<array{name: string, content: string}>  $inputs  Input streams in order ('-' for stdin).
     * @param  array<string, string>  $assignments  -v assignments (values already escape-processed).
     * @param  int  $maxBytes  Largest string the program may build (the executor passes the VFS content ceiling).
     * @return array{output: string, exit_code: int}
     *
     * @throws VfsError invalid_request when a step, field, string or output cap is exceeded.
     */
    public function run(array $inputs, string $fieldSeparator = ' ', array $assignments = [], int $maxBytes = PHP_INT_MAX): array
    {
        $this->maxBytes = max(1, $maxBytes);
        $this->vars = [
            'FS' => $fieldSeparator, 'OFS' => ' ', 'ORS' => "\n", 'RS' => "\n", 'SUBSEP' => "\034",
            'NR' => 0, 'FNR' => 0, 'RSTART' => 0, 'RLENGTH' => -1, 'FILENAME' => '',
            'CONVFMT' => '%.6g', 'OFMT' => '%.6g',
        ];
        foreach ($assignments as $name => $value) {
            $this->vars[$name] = $value;
        }
        $this->out = '';
        $exitCode = 0;

        try {
            foreach ($this->program['begin'] as $block) {
                $this->exec($block);
            }
            if ($this->program['rules'] !== [] || $this->program['end'] !== []) {
                foreach ($inputs as $input) {
                    $this->vars['FILENAME'] = $input['name'] === '-' ? '' : $input['name'];
                    $this->vars['FNR'] = 0;
                    $content = $input['content'];
                    if ($content === '') {
                        continue;
                    }
                    $records = explode("\n", $content);
                    if (str_ends_with($content, "\n")) {
                        array_pop($records);
                    }
                    foreach ($records as $record) {
                        $this->vars['NR'] = $this->num($this->vars['NR']) + 1;
                        $this->vars['FNR'] = $this->num($this->vars['FNR']) + 1;
                        $this->setRecord($record);
                        try {
                            $this->runRules();
                        } catch (VfsAwkSignal $signal) {
                            if ($signal->type !== 'next') {
                                throw $signal;
                            }
                        }
                    }
                }
            }
        } catch (VfsAwkSignal $signal) {
            if ($signal->type !== 'exit') {
                throw VfsError::invalid("awk: {$signal->type} used outside a loop.");
            }
            $exitCode = $signal->exitCode;
        }

        try {
            foreach ($this->program['end'] as $block) {
                $this->exec($block);
            }
        } catch (VfsAwkSignal $signal) {
            if ($signal->type === 'exit') {
                $exitCode = $signal->exitCode;
            }
        }

        return ['output' => $this->out, 'exit_code' => $exitCode];
    }

    private function runRules(): void
    {
        foreach ($this->program['rules'] as $index => $rule) {
            if (! $this->ruleMatches($index, $rule)) {
                continue;
            }
            if ($rule['action'] === null) {
                $this->emit($this->record.$this->str($this->vars['ORS']));
            } else {
                $this->exec($rule['action']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function ruleMatches(int $index, array $rule): bool
    {
        if ($rule['pattern'] === null) {
            return true;
        }
        if ($rule['pattern2'] === null) {
            return $this->patternTrue($rule['pattern']);
        }
        if ($this->rangeActive[$index] ?? false) {
            if ($this->patternTrue($rule['pattern2'])) {
                $this->rangeActive[$index] = false;
            }

            return true;
        }
        if ($this->patternTrue($rule['pattern'])) {
            $this->rangeActive[$index] = ! $this->patternTrue($rule['pattern2']);

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function patternTrue(array $node): bool
    {
        if ($node['k'] === 're') {
            return $this->regexMatch($node['v'], $this->record);
        }

        return $this->truthy($this->eval($node));
    }

    // ------------------------------------------------------------ statements

    /**
     * @param  array<string, mixed>  $node
     */
    private function exec(array $node): void
    {
        $this->tick();
        switch ($node['k']) {
            case 'block':
                foreach ($node['body'] as $statement) {
                    $this->exec($statement);
                }

                return;
            case 'expr':
                $this->eval($node['e']);

                return;
            case 'print':
                if ($node['args'] === []) {
                    $line = $this->record;
                } else {
                    $parts = array_map(fn (array $arg): string => $this->outputString($this->eval($arg)), $node['args']);
                    $line = implode($this->str($this->vars['OFS']), $parts);
                }
                $this->emit($line.$this->str($this->vars['ORS']));

                return;
            case 'printf':
                $args = array_map(fn (array $arg): mixed => $this->eval($arg), $node['args']);
                $format = $this->str(array_shift($args));
                $this->emit(VfsPrintf::awk($format, array_map(fn (mixed $v): string|int|float|null => is_array($v) ? '' : $v, $args)));

                return;
            case 'if':
                if ($this->truthy($this->eval($node['cond']))) {
                    $this->exec($node['then']);
                } elseif ($node['else'] !== null) {
                    $this->exec($node['else']);
                }

                return;
            case 'while':
                while ($this->truthy($this->eval($node['cond']))) {
                    if ($this->loopBody($node['body'])) {
                        break;
                    }
                }

                return;
            case 'do':
                do {
                    if ($this->loopBody($node['body'])) {
                        break;
                    }
                } while ($this->truthy($this->eval($node['cond'])));

                return;
            case 'for':
                if ($node['init'] !== null) {
                    $this->exec($node['init']);
                }
                while ($node['cond'] === null || $this->truthy($this->eval($node['cond']))) {
                    if ($this->loopBody($node['body'])) {
                        break;
                    }
                    if ($node['step'] !== null) {
                        $this->exec($node['step']);
                    }
                }

                return;
            case 'forin':
                foreach (array_keys($this->arrays[$node['array']] ?? []) as $key) {
                    $this->vars[$node['var']] = (string) $key;
                    if ($this->loopBody($node['body'])) {
                        break;
                    }
                }

                return;
            case 'next':
                throw new VfsAwkSignal('next');
            case 'exit':
                throw new VfsAwkSignal('exit', $node['code'] === null ? 0 : (int) $this->num($this->eval($node['code'])));
            case 'break':
                throw new VfsAwkSignal('break');
            case 'continue':
                throw new VfsAwkSignal('continue');
            case 'delete':
                if ($node['subscript'] === null) {
                    $this->arrays[$node['name']] = [];
                } else {
                    unset($this->arrays[$node['name']][$this->subscript($node['subscript'])]);
                }

                return;
        }

        throw VfsError::invalid("awk: unsupported statement {$node['k']}.");
    }

    /**
     * Run a loop body; returns true when the loop must stop (break).
     *
     * @param  array<string, mixed>  $body
     */
    private function loopBody(array $body): bool
    {
        try {
            $this->exec($body);
        } catch (VfsAwkSignal $signal) {
            if ($signal->type === 'break') {
                return true;
            }
            if ($signal->type !== 'continue') {
                throw $signal;
            }
        }

        return false;
    }

    // ------------------------------------------------------------ expressions

    /**
     * @param  array<string, mixed>  $node
     */
    private function eval(array $node): mixed
    {
        $this->tick();
        switch ($node['k']) {
            case 'num':
                return $node['v'];
            case 'str':
                return $node['v'];
            case 're':
                return $this->regexMatch($node['v'], $this->record) ? 1 : 0;
            case 'group':
                return $this->eval($node['e']);
            case 'var':
                if (isset($this->arrays[$node['name']])) {
                    throw VfsError::invalid("awk: attempt to use array {$node['name']} in a scalar context.");
                }

                return $node['name'] === 'NF' ? $this->nf() : ($this->vars[$node['name']] ?? null);
            case 'index':
                return $this->arrays[$node['name']][$this->subscript($node['subscript'])] ?? null;
            case 'field':
                return $this->field((int) $this->num($this->eval($node['e'])));
            case 'assign':
                return $this->assign($node);
            case 'cond':
                return $this->truthy($this->eval($node['cond'])) ? $this->eval($node['then']) : $this->eval($node['else']);
            case 'or':
                return ($this->truthy($this->eval($node['l'])) || $this->truthy($this->eval($node['r']))) ? 1 : 0;
            case 'and':
                return ($this->truthy($this->eval($node['l'])) && $this->truthy($this->eval($node['r']))) ? 1 : 0;
            case 'not':
                return $this->truthy($this->eval($node['e'])) ? 0 : 1;
            case 'neg':
                $value = $this->num($this->eval($node['e']));

                return $node['op'] === '-' ? -$value : $value;
            case 'in':
                return array_key_exists($this->subscript($node['subscript']), $this->arrays[$node['array']] ?? []) ? 1 : 0;
            case 'match':
                $subject = $this->str($this->eval($node['l']));
                $pattern = $node['r']['k'] === 're' ? $node['r']['v'] : $this->str($this->eval($node['r']));
                $matched = $this->regexMatch($pattern, $subject);

                return ($node['negate'] ? ! $matched : $matched) ? 1 : 0;
            case 'cmp':
                return $this->compare($this->eval($node['l']), $this->eval($node['r']), $node['op']) ? 1 : 0;
            case 'concat':
                $left = $this->str($this->eval($node['l']));
                $right = $this->str($this->eval($node['r']));
                $this->assertBytes(strlen($left) + strlen($right), 'string concatenation');

                return $left.$right;
            case 'bin':
                return $this->arith($node['op'], $this->num($this->eval($node['l'])), $this->num($this->eval($node['r'])));
            case 'incr':
                $old = $this->num($this->eval($node['target']));
                $new = $node['op'] === '++' ? $old + 1 : $old - 1;
                $this->store($node['target'], $new);

                return $node['prefix'] ? $new : $old;
            case 'call':
                return $this->call($node['name'], $node['args']);
        }

        throw VfsError::invalid("awk: unsupported expression {$node['k']}.");
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function assign(array $node): mixed
    {
        $value = $this->eval($node['value']);
        if ($node['op'] !== '=') {
            $value = $this->arith(substr($node['op'], 0, 1), $this->num($this->eval($node['target'])), $this->num($value));
        }
        $this->store($node['target'], $value);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function store(array $target, mixed $value): void
    {
        switch ($target['k']) {
            case 'var':
                if ($target['name'] === 'NF') {
                    $this->setNf((int) $this->num($value));

                    return;
                }
                $this->vars[$target['name']] = $value;

                return;
            case 'index':
                $this->arrays[$target['name']][$this->subscript($target['subscript'])] = $value;

                return;
            case 'field':
                $this->setField((int) $this->num($this->eval($target['e'])), $this->str($value));

                return;
            case 'group':
                $this->store($target['e'], $value);

                return;
        }

        throw VfsError::invalid('awk: assignment to a non-variable.');
    }

    private function arith(string $op, int|float $left, int|float $right): int|float
    {
        $result = match ($op) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0 ? throw VfsError::invalid('awk: division by zero.') : $left / $right,
            '%' => $right == 0 ? throw VfsError::invalid('awk: division by zero in %.') : fmod((float) $left, (float) $right),
            '^' => $left ** $right,
            default => throw VfsError::invalid("awk: unknown operator {$op}."),
        };
        if (is_float($result) && is_finite($result) && floor($result) == $result && abs($result) < PHP_INT_MAX) {
            return (int) $result;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $args
     */
    private function call(string $name, array $args): mixed
    {
        $arg = fn (int $i): mixed => isset($args[$i]) ? $this->eval($args[$i]) : null;
        switch ($name) {
            case 'length':
                if ($args === []) {
                    return mb_strlen($this->record);
                }
                if ($args[0]['k'] === 'var' && isset($this->arrays[$args[0]['name']])) {
                    return count($this->arrays[$args[0]['name']]);
                }

                return mb_strlen($this->str($arg(0)));
            case 'substr':
                $s = $this->str($arg(0));
                $len = mb_strlen($s);
                $start = (int) round($this->num($arg(1)));
                $end = isset($args[2]) ? $start + (int) round($this->num($arg(2))) : $len + 1;
                $start = max(1, $start);
                $end = min($len + 1, $end);

                return $end <= $start ? '' : mb_substr($s, $start - 1, $end - $start);
            case 'index':
                $position = mb_strpos($this->str($arg(0)), $this->str($arg(1)));

                return $position === false ? 0 : $position + 1;
            case 'split':
                if (! isset($args[1]) || $args[1]['k'] !== 'var') {
                    throw VfsError::invalid('awk: split requires an array name as its second argument.');
                }
                $separator = isset($args[2]) ? ($args[2]['k'] === 're' ? ['re' => $args[2]['v']] : $this->str($arg(2))) : $this->str($this->vars['FS']);
                $parts = $this->splitString($this->str($arg(0)), $separator);
                $this->arrays[$args[1]['name']] = [];
                foreach ($parts as $i => $part) {
                    $this->arrays[$args[1]['name']][(string) ($i + 1)] = $part;
                }
                unset($this->vars[$args[1]['name']]);

                return count($parts);
            case 'sub':
            case 'gsub':
                return $this->substitute($name === 'gsub', $args);
            case 'match':
                $subject = $this->str($arg(0));
                $pattern = ($args[1]['k'] ?? null) === 're' ? $args[1]['v'] : $this->str($arg(1));
                if (@preg_match($this->compileRegex($pattern), $subject, $m, PREG_OFFSET_CAPTURE) === 1) {
                    $this->vars['RSTART'] = mb_strlen(substr($subject, 0, $m[0][1])) + 1;
                    $this->vars['RLENGTH'] = mb_strlen($m[0][0]);
                } else {
                    $this->vars['RSTART'] = 0;
                    $this->vars['RLENGTH'] = -1;
                }

                return $this->vars['RSTART'];
            case 'sprintf':
                $values = array_map(fn (array $a): mixed => $this->eval($a), $args);
                $format = $this->str(array_shift($values));

                return VfsPrintf::awk($format, array_map(fn (mixed $v): string|int|float|null => is_array($v) ? '' : $v, $values));
            case 'tolower':
                return mb_strtolower($this->str($arg(0)));
            case 'toupper':
                return mb_strtoupper($this->str($arg(0)));
            case 'int':
                $n = $this->num($arg(0));

                return (int) ($n < 0 ? ceil($n) : floor($n));
            case 'sqrt':
                return sqrt((float) $this->num($arg(0)));
            case 'exp':
                return exp((float) $this->num($arg(0)));
            case 'log':
                return log((float) $this->num($arg(0)));
            case 'sin':
                return sin((float) $this->num($arg(0)));
            case 'cos':
                return cos((float) $this->num($arg(0)));
            case 'atan2':
                return atan2((float) $this->num($arg(0)), (float) $this->num($arg(1)));
            case 'rand':
                return mt_rand() / mt_getrandmax();
            case 'srand':
                mt_srand(isset($args[0]) ? (int) $this->num($arg(0)) : time());

                return 0;
        }

        throw VfsError::unsupported("awk {$name}() is not supported in VFS awk.");
    }

    /**
     * @param  list<array<string, mixed>>  $args
     */
    private function substitute(bool $global, array $args): int
    {
        if (count($args) < 2) {
            throw VfsError::invalid('awk: sub/gsub require a regex and a replacement.');
        }
        $pattern = $args[0]['k'] === 're' ? $args[0]['v'] : $this->str($this->eval($args[0]));
        $replacement = $this->str($this->eval($args[1]));
        $target = $args[2] ?? ['k' => 'field', 'e' => ['k' => 'num', 'v' => 0]];
        $subject = $this->str($this->eval($target));
        $count = 0;
        // Running result size, checked per match before it is appended.
        $size = strlen($subject);
        $result = @preg_replace_callback($this->compileRegex($pattern), function (array $m) use ($replacement, &$size): string {
            $size -= strlen($m[0]);
            $out = '';
            $length = strlen($replacement);
            for ($i = 0; $i < $length; $i++) {
                $c = $replacement[$i];
                if ($c === '\\' && $i + 1 < $length && in_array($replacement[$i + 1], ['&', '\\'], true)) {
                    $piece = $replacement[++$i];
                } else {
                    $piece = $c === '&' ? $m[0] : $c;
                }
                $size += strlen($piece);
                $this->assertBytes($size, 'sub/gsub result');
                $out .= $piece;
            }

            return $out;
        }, $subject, $global ? -1 : 1, $count);
        if ($result === null) {
            throw VfsError::invalid('awk: regular expression failed to run.');
        }
        if ($count > 0) {
            $this->store($target, $result);
        }

        return $count;
    }

    // ------------------------------------------------------------ fields

    private function setRecord(string $record): void
    {
        $this->record = $record;
        $this->fields = null;
    }

    /**
     * @return list<string>
     */
    private function fields(): array
    {
        if ($this->fields === null) {
            $this->fields = $this->splitString($this->record, $this->str($this->vars['FS']));
        }

        return $this->fields;
    }

    private function nf(): int
    {
        return count($this->fields());
    }

    private function field(int $index): string
    {
        if ($index < 0) {
            throw VfsError::invalid("awk: attempt to access field {$index}.");
        }
        if ($index === 0) {
            return $this->record;
        }

        return $this->fields()[$index - 1] ?? '';
    }

    private function setField(int $index, string $value): void
    {
        if ($index < 0) {
            throw VfsError::invalid("awk: attempt to assign field {$index}.");
        }
        // Invariant: checked before the field list is padded up to $index.
        $this->assertFieldCount($index);
        if ($index === 0) {
            $this->setRecord($value);

            return;
        }
        $fields = $this->fields();
        while (count($fields) < $index) {
            $fields[] = '';
        }
        $fields[$index - 1] = $value;
        $this->fields = $fields;
        $this->record = implode($this->str($this->vars['OFS']), $fields);
    }

    private function setNf(int $nf): void
    {
        $this->assertFieldCount($nf);
        $fields = array_slice($this->fields(), 0, max(0, $nf));
        while (count($fields) < $nf) {
            $fields[] = '';
        }
        $this->fields = $fields;
        $this->record = implode($this->str($this->vars['OFS']), $fields);
    }

    /**
     * Split by awk FS rules: " " = runs of blanks with leading/trailing blanks
     * ignored; any other single character = that literal character; longer
     * separators (or a regex literal) = extended regex.
     *
     * @param  string|array{re: string}  $separator
     * @return list<string>
     */
    private function splitString(string $text, string|array $separator): array
    {
        if ($text === '') {
            return [];
        }
        if (is_array($separator)) {
            return preg_split($this->compileRegex($separator['re']), $text) ?: [$text];
        }
        if ($separator === ' ') {
            $trimmed = trim($text, " \t\n");

            return $trimmed === '' ? [] : (preg_split('/[ \t\n]+/', $trimmed) ?: []);
        }
        if (strlen($separator) === 1 || $separator === '\\t') {
            return explode($separator === '\\t' ? "\t" : $separator, $text);
        }

        return preg_split($this->compileRegex($separator), $text) ?: [$text];
    }

    // ------------------------------------------------------------ values

    private function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (is_finite($value) && floor($value) == $value && abs($value) < 1e16) {
                return (string) (int) $value;
            }

            return VfsPrintf::awk($this->vars['CONVFMT'] ?? '%.6g', [$value]);
        }

        return (string) $value;
    }

    private function outputString(mixed $value): string
    {
        if (is_float($value) && ! (is_finite($value) && floor($value) == $value && abs($value) < 1e16)) {
            return VfsPrintf::awk($this->str($this->vars['OFMT'] ?? '%.6g'), [$value]);
        }

        return $this->str($value);
    }

    private function num(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value === null) {
            return 0;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', (string) $value, $m) === 1) {
            $number = $m[1];

            return (str_contains($number, '.') || stripos($number, 'e') !== false) ? (float) $number : (int) $number;
        }

        return 0;
    }

    private function looksNumeric(mixed $value): bool
    {
        return $value === null || is_int($value) || is_float($value)
            || preg_match('/^\s*[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?\s*$/', (string) $value) === 1;
    }

    private function truthy(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if ($value === '') {
            return false;
        }

        return $this->looksNumeric($value) ? $this->num($value) != 0 : true;
    }

    private function compare(mixed $left, mixed $right, string $op): bool
    {
        if ($this->looksNumeric($left) && $this->looksNumeric($right)) {
            $cmp = $this->num($left) <=> $this->num($right);
        } else {
            $cmp = strcmp($this->str($left), $this->str($right)) <=> 0;
        }

        return match ($op) {
            '<' => $cmp < 0,
            '<=' => $cmp <= 0,
            '>' => $cmp > 0,
            '>=' => $cmp >= 0,
            '==' => $cmp === 0,
            default => $cmp !== 0,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $subscript
     */
    private function subscript(array $subscript): string
    {
        return implode($this->str($this->vars['SUBSEP']), array_map(fn (array $node): string => $this->str($this->eval($node)), $subscript));
    }

    private function regexMatch(string $pattern, string $subject): bool
    {
        $result = @preg_match($this->compileRegex($pattern), $subject);
        if ($result === false) {
            throw VfsError::invalid("awk: regular expression /{$pattern}/ failed to run.");
        }

        return $result === 1;
    }

    private function compileRegex(string $pattern): string
    {
        if (isset($this->regexCache[$pattern])) {
            return $this->regexCache[$pattern];
        }
        $body = str_replace('~', '\\~', $pattern);
        foreach (['~'.$body.'~u', '~'.$body.'~'] as $candidate) {
            if (@preg_match($candidate, '') !== false) {
                return $this->regexCache[$pattern] = $candidate;
            }
        }

        throw VfsError::invalid("awk: invalid regular expression /{$pattern}/.");
    }

    /**
     * @throws VfsError when a program sets more than MAX_FIELDS fields.
     */
    private function assertFieldCount(int $count): void
    {
        if ($count > self::MAX_FIELDS) {
            throw VfsError::invalid("awk: field {$count} exceeds the VFS limit of ".self::MAX_FIELDS.' fields.', ['hint' => 'Assign a smaller NF or field index.']);
        }
    }

    /**
     * @throws VfsError when a string the program builds would exceed the run's byte limit.
     */
    private function assertBytes(int $bytes, string $what): void
    {
        if ($bytes > $this->maxBytes) {
            throw VfsError::invalid("awk {$what} would exceed {$this->maxBytes} bytes.", ['max_bytes' => $this->maxBytes, 'hint' => 'A loop is growing a string without bound; narrow the program.']);
        }
    }

    private function emit(string $text): void
    {
        $this->out .= $text;
        if (strlen($this->out) > self::MAX_OUTPUT_BYTES) {
            throw VfsError::invalid('awk output exceeded the VFS limit.', ['hint' => 'Filter more narrowly or summarize in END.']);
        }
    }

    private function tick(): void
    {
        if (++$this->steps > self::MAX_STEPS) {
            throw VfsError::invalid('awk program exceeded the VFS step budget.', ['hint' => 'Check for an infinite loop, or narrow the input.']);
        }
    }
}
