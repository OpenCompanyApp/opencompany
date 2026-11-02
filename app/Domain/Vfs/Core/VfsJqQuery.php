<?php

namespace App\Domain\Vfs\Core;

/**
 * Pure jq interpreter for the VFS shell (a practical subset of jq 1.7).
 *
 * Owns parsing a jq program and evaluating it over decoded JSON values.
 * Values keep JSON's object/array distinction (objects are stdClass, arrays
 * are PHP lists), so `{}` round-trips as `{}`.
 *
 * Supported: `.`, `..`, `.a`, `."a"`, `.[e]`, `.[a:b]`, `.[]`, `?`, `|`, `,`,
 * literals, string interpolation `"\(e)"`, `[e]`, `{a: e, "b": e, (e): e, a}`,
 * `+ - * / %`, `== != < <= > >=`, `and`, `or`, `//`, `if/elif/else/end`,
 * `@csv @tsv @json @text @base64 @base64d @uri @html @sh`, and the built-ins
 * listed in BUILTINS.
 *
 * Not supported (fail with `unsupported` and a hint): variables (`as $x`,
 * `--arg`), `reduce`/`foreach`, `try`/`catch`, user `def`, and assignment
 * operators (`=`, `|=`, `+=`, `del`) — use Code Mode for JSON rewrites.
 *
 * Bounded by MAX_STEPS evaluations and MAX_OUTPUTS results.
 *
 * Resource invariant: MAX_STEPS bounds evaluations, not memory. One step
 * can materialize a huge value (`range(100000000)`, a doubling `. + .`,
 * a `gsub`, the cartesian product of two generators), so every generated
 * list is held to MAX_ITEMS and every built string, array or JSON encoding
 * to the run's byte limit (the VFS content ceiling), each checked BEFORE
 * the allocation is made. Exceeding a cap is an invalid_request VfsError,
 * never a PHP memory fatal.
 */
final class VfsJqQuery
{
    private const MAX_STEPS = 2_000_000;

    private const MAX_OUTPUTS = 100_000;

    /** Largest list one expression may generate (range, pipes, `..`, flatten). */
    public const MAX_ITEMS = 1_000_000;

    private int $steps = 0;

    /** Byte limit of the run in progress for strings, arrays and encodings. */
    private int $maxBytes = PHP_INT_MAX;

    /** @param array<string, mixed> $ast */
    private function __construct(private readonly array $ast) {}

    public static function parse(string $program): self
    {
        return new self((new VfsJqParser($program))->parse());
    }

    /**
     * Evaluate the program over one input value.
     *
     * $maxBytes bounds every string, array and JSON encoding the program
     * builds (the executor passes the VFS content ceiling).
     *
     * @return list<mixed>
     *
     * @throws VfsError invalid_request when a step, item or byte cap is exceeded.
     */
    public function run(mixed $input, int $maxBytes): array
    {
        $this->steps = 0;
        $this->maxBytes = max(1, $maxBytes);
        $outputs = $this->eval($this->ast, $input);
        if (count($outputs) > self::MAX_OUTPUTS) {
            throw VfsError::invalid('jq produced too many results for the VFS budget.');
        }

        return $outputs;
    }

    /**
     * Encode a value as jq does: 2-space pretty printing or compact.
     *
     * With $maxBytes, the value's encoded size is estimated first (a walk
     * that stops as soon as it passes the limit), so a small value that
     * shares one large array many times cannot make json_encode allocate
     * gigabytes.
     *
     * @throws VfsError invalid_request when the value cannot be encoded or is too large.
     */
    public static function encode(mixed $value, bool $compact, int $maxBytes = PHP_INT_MAX): string
    {
        if ($maxBytes < PHP_INT_MAX) {
            // Pretty printing at most triples the compact size (indentation).
            self::assertEncodedSizeWithin($value, $compact ? $maxBytes : intdiv($maxBytes, 3));
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | ($compact ? 0 : JSON_PRETTY_PRINT));
        if ($json === false) {
            throw VfsError::invalid('jq: value cannot be encoded as JSON.');
        }
        if (! $compact) {
            $json = preg_replace_callback('/^(?: {4})+/m', fn (array $m): string => str_repeat(' ', intdiv(strlen($m[0]), 2)), $json) ?? $json;
        }

        return $json;
    }

    /**
     * Decode a JSON text stream (one document, NDJSON, or concatenated
     * pretty-printed documents) into values.
     *
     * @return list<mixed>
     */
    public static function decodeStream(string $text): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }
        $value = json_decode($trimmed, false, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() === JSON_ERROR_NONE) {
            return [$value];
        }

        $values = [];
        $buffer = '';
        foreach (explode("\n", $trimmed) as $lineNumber => $line) {
            $buffer .= ($buffer === '' ? '' : "\n").$line;
            if (trim($buffer) === '') {
                $buffer = '';

                continue;
            }
            $decoded = json_decode($buffer, false, 512, JSON_BIGINT_AS_STRING);
            if (json_last_error() === JSON_ERROR_NONE) {
                $values[] = $decoded;
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            throw VfsError::invalid('jq input is not valid JSON.', ['hint' => 'Check the input with cat; jq accepts one JSON document, NDJSON, or concatenated documents.']);
        }

        return $values;
    }

    // ---------------------------------------------------------------- eval

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    private function eval(array $node, mixed $input): array
    {
        if (++$this->steps > self::MAX_STEPS) {
            throw VfsError::invalid('jq program exceeded the VFS step budget.');
        }

        switch ($node['k']) {
            case 'identity':
                return [$input];
            case 'recurse':
                return $this->recurseValues($input);
            case 'literal':
                return [$node['v']];
            case 'string':
                return $this->interpolate($node['parts'], $input);
            case 'format':
                return [$this->format($node['name'], $input)];
            case 'pipe':
                $out = [];
                foreach ($this->eval($node['l'], $input) as $value) {
                    $results = $this->eval($node['r'], $value);
                    $this->assertItems(count($out) + count($results));
                    array_push($out, ...$results);
                }

                return $out;
            case 'comma':
                $left = $this->eval($node['l'], $input);
                $right = $this->eval($node['r'], $input);
                $this->assertItems(count($left) + count($right));

                return [...$left, ...$right];
            case 'field':
                return array_map(fn (mixed $value): mixed => $this->field($value, $node['name'], $node['optional'] ?? false), $this->eval($node['target'], $input));
            case 'index':
                $out = [];
                foreach ($this->eval($node['target'], $input) as $value) {
                    foreach ($this->eval($node['index'], $input) as $key) {
                        $this->assertItems(count($out) + 1);
                        try {
                            $out[] = $this->indexValue($value, $key);
                        } catch (VfsError $e) {
                            if (! ($node['optional'] ?? false)) {
                                throw $e;
                            }
                        }
                    }
                }

                return $out;
            case 'slice':
                $out = [];
                foreach ($this->eval($node['target'], $input) as $value) {
                    $from = $node['from'] === null ? null : $this->eval($node['from'], $input)[0] ?? null;
                    $to = $node['to'] === null ? null : $this->eval($node['to'], $input)[0] ?? null;
                    $out[] = $this->slice($value, $from, $to);
                }

                return $out;
            case 'iterate':
                $out = [];
                foreach ($this->eval($node['target'], $input) as $value) {
                    if (is_array($value)) {
                        $this->assertItems(count($out) + count($value));
                        array_push($out, ...$value);
                    } elseif ($value instanceof \stdClass) {
                        $this->assertItems(count($out) + count(get_object_vars($value)));
                        array_push($out, ...array_values(get_object_vars($value)));
                    } elseif (! ($node['optional'] ?? false)) {
                        throw VfsError::invalid('jq: cannot iterate over '.$this->typeOf($value).'.');
                    }
                }

                return $out;
            case 'try':
                try {
                    return $this->eval($node['e'], $input);
                } catch (VfsError $e) {
                    if ($e->errorCode === 'unsupported') {
                        throw $e;
                    }

                    return [];
                }
            case 'array':
                return [$node['e'] === null ? [] : $this->eval($node['e'], $input)];
            case 'object':
                return $this->construct($node['entries'], $input);
            case 'neg':
                return array_map(function (mixed $value): int|float {
                    if (! is_int($value) && ! is_float($value)) {
                        throw VfsError::invalid('jq: cannot negate '.$this->typeOf($value).'.');
                    }

                    return -$value;
                }, $this->eval($node['e'], $input));
            case 'binop':
                return $this->binary($node, $input);
            case 'and':
            case 'or':
                $out = [];
                foreach ($this->eval($node['l'], $input) as $left) {
                    $leftTrue = $this->truthy($left);
                    if ($node['k'] === 'and' && ! $leftTrue) {
                        $out[] = false;

                        continue;
                    }
                    if ($node['k'] === 'or' && $leftTrue) {
                        $out[] = true;

                        continue;
                    }
                    foreach ($this->eval($node['r'], $input) as $right) {
                        $this->assertItems(count($out) + 1);
                        $out[] = $this->truthy($right);
                    }
                }

                return $out;
            case 'alt':
                $left = [];
                try {
                    $left = array_values(array_filter($this->eval($node['l'], $input), fn (mixed $v): bool => $v !== null && $v !== false));
                } catch (VfsError $e) {
                    if ($e->errorCode === 'unsupported') {
                        throw $e;
                    }
                }

                return $left !== [] ? $left : $this->eval($node['r'], $input);
            case 'if':
                $out = [];
                foreach ($this->eval($node['cond'], $input) as $condition) {
                    if ($this->truthy($condition) || $node['else'] !== null) {
                        $branch = $this->eval($this->truthy($condition) ? $node['then'] : $node['else'], $input);
                        $this->assertItems(count($out) + count($branch));
                        array_push($out, ...$branch);
                    } else {
                        $out[] = $input;
                    }
                }

                return $out;
            case 'call':
                return $this->call($node['name'], $node['args'], $input);
        }

        throw VfsError::invalid("jq: unsupported expression {$node['k']}.");
    }

    /**
     * @param  list<array<string, mixed>>  $args
     * @return list<mixed>
     */
    private function call(string $name, array $args, mixed $input): array
    {
        $one = fn (int $i): mixed => $this->eval($args[$i], $input)[0] ?? null;
        $arity = count($args);

        switch ($name.'/'.$arity) {
            case 'empty/0': return [];
            case 'not/0': return [! $this->truthy($input)];
            case 'length/0':
                return [match (true) {
                    $input === null => 0,
                    is_bool($input) => throw VfsError::invalid('jq: boolean has no length.'),
                    is_int($input) || is_float($input) => abs($input),
                    is_string($input) => mb_strlen($input),
                    is_array($input) => count($input),
                    default => count(get_object_vars($input)),
                }];
            case 'utf8bytelength/0': return [strlen($this->requireString($input, $name))];
            case 'keys/0':
            case 'keys_unsorted/0':
                if (is_array($input)) {
                    return [array_keys($input)];
                }
                $keys = array_map('strval', array_keys(get_object_vars($this->requireObject($input, $name))));
                if ($name === 'keys') {
                    sort($keys, SORT_STRING);
                }

                return [$keys];
            case 'values/0': return $input === null ? [] : [$input];
            case 'has/1':
                $key = $one(0);
                if (is_array($input)) {
                    return [is_int($key) && $key >= 0 && $key < count($input)];
                }

                return [property_exists($this->requireObject($input, $name), (string) $key)];
            case 'in/1':
                $object = $one(0);

                return [is_array($object) ? (is_int($input) && $input >= 0 && $input < count($object)) : property_exists($this->requireObject($object, $name), (string) $input)];
            case 'map/1':
                $out = [];
                foreach ($this->iterate($input, $name) as $item) {
                    array_push($out, ...$this->eval($args[0], $item));
                }

                return [$out];
            case 'map_values/1':
                if (is_array($input)) {
                    return [array_map(fn (mixed $item): mixed => $this->eval($args[0], $item)[0] ?? null, $input)];
                }
                $object = new \stdClass;
                foreach (get_object_vars($this->requireObject($input, $name)) as $key => $item) {
                    $result = $this->eval($args[0], $item);
                    if ($result !== []) {
                        $object->{$key} = $result[0];
                    }
                }

                return [$object];
            case 'select/1':
                $out = [];
                foreach ($this->eval($args[0], $input) as $condition) {
                    if ($this->truthy($condition)) {
                        $out[] = $input;
                    }
                }

                return $out;
            case 'recurse/0': return $this->recurseValues($input);
            case 'add/0':
                $items = $this->iterate($input, $name);
                if ($items === []) {
                    return [null];
                }
                $sum = array_shift($items);
                foreach ($items as $item) {
                    $sum = $this->add($sum, $item);
                }

                return [$sum];
            case 'any/0': return [array_reduce($this->iterate($input, $name), fn (bool $carry, mixed $v): bool => $carry || $this->truthy($v), false)];
            case 'all/0': return [array_reduce($this->iterate($input, $name), fn (bool $carry, mixed $v): bool => $carry && $this->truthy($v), true)];
            case 'any/1':
                foreach ($this->iterate($input, $name) as $item) {
                    foreach ($this->eval($args[0], $item) as $v) {
                        if ($this->truthy($v)) {
                            return [true];
                        }
                    }
                }

                return [false];
            case 'all/1':
                foreach ($this->iterate($input, $name) as $item) {
                    foreach ($this->eval($args[0], $item) as $v) {
                        if (! $this->truthy($v)) {
                            return [false];
                        }
                    }
                }

                return [true];
            case 'range/1':
                $upto = (int) $one(0);
                // Invariant: the count is checked before range() allocates.
                $this->assertItems(max(0, $upto), 'range');

                return $upto <= 0 ? [] : range(0, $upto - 1);
            case 'range/2':
                $from = (int) $one(0);
                $to = (int) $one(1);
                $this->assertItems($to <= $from ? 0 : (int) min((float) PHP_INT_MAX, (float) $to - (float) $from), 'range');

                return $to <= $from ? [] : range($from, $to - 1);
            case 'floor/0': return [(int) floor($this->requireNumber($input, $name))];
            case 'ceil/0': return [(int) ceil($this->requireNumber($input, $name))];
            case 'round/0': return [(int) round($this->requireNumber($input, $name))];
            case 'abs/0': return [abs($this->requireNumber($input, $name))];
            case 'sqrt/0': return [sqrt($this->requireNumber($input, $name))];
            case 'tostring/0': return [is_string($input) ? $input : self::encode($input, true, $this->maxBytes)];
            case 'tonumber/0':
                if (is_int($input) || is_float($input)) {
                    return [$input];
                }
                if (is_string($input) && is_numeric(trim($input))) {
                    return [trim($input) + 0];
                }
                throw VfsError::invalid('jq: cannot parse '.self::encode($input, true).' as a number.');
            case 'type/0': return [$this->typeOf($input)];
            case 'ascii_downcase/0': return [strtolower($this->requireString($input, $name))];
            case 'ascii_upcase/0': return [strtoupper($this->requireString($input, $name))];
            case 'ltrimstr/1':
                $prefix = $one(0);

                return [is_string($input) && is_string($prefix) && str_starts_with($input, $prefix) ? substr($input, strlen($prefix)) : $input];
            case 'rtrimstr/1':
                $suffix = $one(0);

                return [is_string($input) && is_string($suffix) && $suffix !== '' && str_ends_with($input, $suffix) ? substr($input, 0, -strlen($suffix)) : $input];
            case 'trim/0': return [trim($this->requireString($input, $name))];
            case 'ltrim/0': return [ltrim($this->requireString($input, $name))];
            case 'rtrim/0': return [rtrim($this->requireString($input, $name))];
            case 'startswith/1': return [str_starts_with($this->requireString($input, $name), (string) $one(0))];
            case 'endswith/1': return [str_ends_with($this->requireString($input, $name), (string) $one(0))];
            case 'split/1':
                $separator = (string) $one(0);
                $text = $this->requireString($input, $name);

                return [$text === '' ? [] : ($separator === '' ? mb_str_split($text) : explode($separator, $text))];
            case 'join/1':
                $separator = (string) $one(0);

                return [implode($separator, array_map(fn (mixed $v): string => match (true) {
                    $v === null => '',
                    is_bool($v) => $v ? 'true' : 'false',
                    is_scalar($v) => (string) $v,
                    default => throw VfsError::invalid('jq: cannot join '.$this->typeOf($v).'.'),
                }, $this->iterate($input, $name)))];
            case 'test/1':
            case 'test/2':
                return [preg_match($this->regex((string) $one(0), $arity === 2 ? (string) $one(1) : ''), $this->requireString($input, $name)) === 1];
            case 'sub/2':
            case 'gsub/2':
            case 'sub/3':
            case 'gsub/3':
                $flags = $arity === 3 ? (string) $one(2) : '';
                $limit = $name === 'gsub' || str_contains($flags, 'g') ? -1 : 1;
                $replacement = (string) $one(1);
                $subject = $this->requireString($input, $name);
                // Running size checked per match, before the result exists.
                $size = strlen($subject);
                $result = preg_replace_callback($this->regex((string) $one(0), str_replace('g', '', $flags)), function (array $match) use ($replacement, $name, &$size): string {
                    $size += strlen($replacement) - strlen($match[0]);
                    $this->assertBytes($size, $name);

                    return $replacement;
                }, $subject, $limit);
                if ($result === null) {
                    throw VfsError::invalid("jq: {$name} regex failed to run (too complex or invalid).");
                }

                return [$result];
            case 'contains/1': return [$this->contains($input, $one(0))];
            case 'inside/1': return [$this->contains($one(0), $input)];
            case 'flatten/0':
            case 'flatten/1':
                $count = 0;

                return [$this->flatten($this->requireArray($input, $name), $arity === 1 ? (int) $one(0) : 1_000, $count)];
            case 'reverse/0': return [is_string($input) ? implode('', array_reverse(mb_str_split($input))) : array_reverse($this->requireArray($input ?? [], $name))];
            case 'sort/0':
                $items = $this->requireArray($input, $name);
                usort($items, fn (mixed $a, mixed $b): int => $this->compare($a, $b));

                return [$items];
            case 'sort_by/1':
                return [array_column($this->keyed($input, $args[0], $name, true), 'v')];
            case 'group_by/1':
                $groups = [];
                $lastKey = null;
                foreach ($this->keyed($input, $args[0], $name, true) as $index => $pair) {
                    if ($index === 0 || $this->compare($pair['k'], $lastKey) !== 0) {
                        $groups[] = [];
                    }
                    $groups[count($groups) - 1][] = $pair['v'];
                    $lastKey = $pair['k'];
                }

                return [$groups];
            case 'unique/0':
                $items = $this->requireArray($input, $name);
                usort($items, fn (mixed $a, mixed $b): int => $this->compare($a, $b));

                return [$this->dedupeSorted(array_map(fn (mixed $v): array => ['k' => $v, 'v' => $v], $items))];
            case 'unique_by/1':
                return [$this->dedupeSorted($this->keyed($input, $args[0], $name, true))];
            case 'min/0':
            case 'max/0':
                $items = $this->requireArray($input, $name);
                if ($items === []) {
                    return [null];
                }
                usort($items, fn (mixed $a, mixed $b): int => $this->compare($a, $b));

                return [$name === 'min' ? $items[0] : $items[count($items) - 1]];
            case 'min_by/1':
            case 'max_by/1':
                $pairs = $this->keyed($input, $args[0], $name, true);
                if ($pairs === []) {
                    return [null];
                }

                return [$name === 'min_by' ? $pairs[0]['v'] : $pairs[count($pairs) - 1]['v']];
            case 'first/0': return [$this->requireArray($input, $name)[0] ?? null];
            case 'last/0':
                $items = $this->requireArray($input, $name);

                return [$items === [] ? null : $items[count($items) - 1]];
            case 'first/1':
                $all = $this->eval($args[0], $input);

                return $all === [] ? [] : [$all[0]];
            case 'last/1':
                $all = $this->eval($args[0], $input);

                return $all === [] ? [] : [$all[count($all) - 1]];
            case 'nth/1': return [$this->requireArray($input, $name)[(int) $one(0)] ?? null];
            case 'limit/2':
                return array_slice($this->eval($args[1], $input), 0, max(0, (int) $one(0)));
            case 'to_entries/0':
                $out = [];
                foreach (is_array($input) ? $input : get_object_vars($this->requireObject($input, $name)) as $key => $value) {
                    $out[] = (object) ['key' => is_array($input) ? $key : (string) $key, 'value' => $value];
                }

                return [$out];
            case 'from_entries/0':
                $object = new \stdClass;
                foreach ($this->requireArray($input, $name) as $entry) {
                    $entry = $entry instanceof \stdClass ? get_object_vars($entry) : (array) $entry;
                    $key = $entry['key'] ?? $entry['k'] ?? $entry['name'] ?? $entry['Name'] ?? $entry['Key'] ?? null;
                    if ($key === null) {
                        throw VfsError::invalid('jq: from_entries needs objects with a "key".');
                    }
                    $object->{is_bool($key) ? ($key ? 'true' : 'false') : (string) $key} = $entry['value'] ?? $entry['v'] ?? $entry['Value'] ?? null;
                }

                return [$object];
            case 'with_entries/1':
                [$entries] = $this->call('to_entries', [], $input);
                $mapped = [];
                foreach ($entries as $entry) {
                    array_push($mapped, ...$this->eval($args[0], $entry));
                }

                return $this->call('from_entries', [], $mapped);
            case 'tojson/0': return [self::encode($input, true, $this->maxBytes)];
            case 'fromjson/0':
                $decoded = json_decode($this->requireString($input, $name), false);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw VfsError::invalid('jq: fromjson input is not valid JSON.');
                }

                return [$decoded];
            case 'error/0':
            case 'error/1':
                throw VfsError::invalid('jq: error: '.($arity === 1 ? (is_string($one(0)) ? $one(0) : self::encode($one(0), true)) : (is_string($input) ? $input : self::encode($input, true))));
            case 'numbers/0': return is_int($input) || is_float($input) ? [$input] : [];
            case 'strings/0': return is_string($input) ? [$input] : [];
            case 'booleans/0': return is_bool($input) ? [$input] : [];
            case 'nulls/0': return $input === null ? [$input] : [];
            case 'arrays/0': return is_array($input) ? [$input] : [];
            case 'objects/0': return $input instanceof \stdClass ? [$input] : [];
            case 'iterables/0': return is_array($input) || $input instanceof \stdClass ? [$input] : [];
            case 'scalars/0': return is_array($input) || $input instanceof \stdClass ? [] : [$input];
            case 'tostream/0':
            case 'paths/0':
            case 'getpath/1':
            case 'del/1':
            case 'to_number/0':
                break;
        }

        throw VfsError::unsupported("jq function {$name}/{$arity} is not supported in VFS jq.", [
            'supported_functions' => VfsJqParser::BUILTINS,
            'hint' => 'Use Code Mode (code_exec) for JSON rewrites (del, assignment, reduce, variables).',
        ]);
    }

    /**
     * @param  list<array{key: array<string, mixed>|string, value: array<string, mixed>|null}>  $entries
     * @return list<\stdClass>
     */
    private function construct(array $entries, mixed $input): array
    {
        $objects = [new \stdClass];
        foreach ($entries as $entry) {
            $keys = is_string($entry['key']) ? [$entry['key']] : $this->eval($entry['key'], $input);
            $values = $entry['value'] === null
                ? [$this->field($input, is_string($entry['key']) ? $entry['key'] : '', false)]
                : $this->eval($entry['value'], $input);
            $next = [];
            foreach ($objects as $object) {
                foreach ($keys as $key) {
                    if (! is_string($key)) {
                        throw VfsError::invalid('jq: object keys must be strings, got '.$this->typeOf($key).'.');
                    }
                    foreach ($values as $value) {
                        $copy = clone $object;
                        $copy->{$key} = $value;
                        $next[] = $copy;
                    }
                }
            }
            $objects = $next;
        }

        return $objects;
    }

    /**
     * Fail before a list of $count items is built when it passes MAX_ITEMS.
     *
     * @throws VfsError
     */
    private function assertItems(int $count, string $what = 'expression'): void
    {
        if ($count > self::MAX_ITEMS) {
            throw VfsError::invalid("jq {$what} would produce {$count} values; the VFS limit is ".self::MAX_ITEMS.'.', [
                'hint' => 'Narrow the input or use limit/first on a smaller range.',
            ]);
        }
    }

    /**
     * Fail before a string or encoding of $bytes bytes is built when it
     * passes the run's byte limit.
     *
     * @throws VfsError
     */
    private function assertBytes(int $bytes, string $what): void
    {
        if ($bytes > $this->maxBytes) {
            throw VfsError::invalid("jq {$what} would exceed {$this->maxBytes} bytes.", ['max_bytes' => $this->maxBytes]);
        }
    }

    /**
     * Estimate the compact JSON size of $value and fail once it passes
     * $maxBytes. The walk stops at the limit, so its own cost is bounded
     * by $maxBytes even when the value shares one array many times.
     *
     * @throws VfsError
     */
    private static function assertEncodedSizeWithin(mixed $value, int $maxBytes): void
    {
        $size = 0;
        $stack = [$value];
        while ($stack !== []) {
            $item = array_pop($stack);
            if (is_array($item) || $item instanceof \stdClass) {
                $members = is_array($item) ? $item : get_object_vars($item);
                $size += 2 + count($members);
                if (! is_array($item)) {
                    foreach (array_keys($members) as $key) {
                        $size += strlen((string) $key) + 3;
                    }
                }
                if ($size > $maxBytes) {
                    break;
                }
                foreach ($members as $member) {
                    $stack[] = $member;
                }
            } else {
                $size += is_string($item) ? strlen($item) + 2 : 24;
            }
            if ($size > $maxBytes) {
                break;
            }
        }
        if ($size > $maxBytes) {
            throw VfsError::invalid("jq value is too large to encode (over {$maxBytes} bytes).", ['max_bytes' => $maxBytes, 'hint' => 'Select a smaller part of the input.']);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    private function binary(array $node, mixed $input): array
    {
        $out = [];
        foreach ($this->eval($node['r'], $input) as $right) {
            foreach ($this->eval($node['l'], $input) as $left) {
                $this->assertItems(count($out) + 1);
                $out[] = match ($node['op']) {
                    '+' => $this->add($left, $right),
                    '-' => $this->subtract($left, $right),
                    '*' => $this->multiply($left, $right),
                    '/' => $this->divide($left, $right),
                    '%' => $this->modulo($left, $right),
                    '==' => $this->compare($left, $right) === 0,
                    '!=' => $this->compare($left, $right) !== 0,
                    '<' => $this->compare($left, $right) < 0,
                    '<=' => $this->compare($left, $right) <= 0,
                    '>' => $this->compare($left, $right) > 0,
                    '>=' => $this->compare($left, $right) >= 0,
                    default => throw VfsError::invalid("jq: unknown operator {$node['op']}."),
                };
            }
        }

        return $out;
    }

    private function add(mixed $a, mixed $b): mixed
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a + $b;
        }
        if (is_string($a) && is_string($b)) {
            $this->assertBytes(strlen($a) + strlen($b), 'string');

            return $a.$b;
        }
        if (is_array($a) && is_array($b)) {
            $this->assertItems(count($a) + count($b), 'array');

            return [...$a, ...$b];
        }
        if ($a instanceof \stdClass && $b instanceof \stdClass) {
            return (object) array_merge(get_object_vars($a), get_object_vars($b));
        }

        throw VfsError::invalid('jq: '.$this->typeOf($a).' and '.$this->typeOf($b).' cannot be added.');
    }

    private function subtract(mixed $a, mixed $b): mixed
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a - $b;
        }
        if (is_array($a) && is_array($b)) {
            return array_values(array_filter($a, function (mixed $item) use ($b): bool {
                foreach ($b as $remove) {
                    if ($this->compare($item, $remove) === 0) {
                        return false;
                    }
                }

                return true;
            }));
        }

        throw VfsError::invalid('jq: '.$this->typeOf($a).' and '.$this->typeOf($b).' cannot be subtracted.');
    }

    private function multiply(mixed $a, mixed $b): mixed
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a * $b;
        }
        if ($a instanceof \stdClass && $b instanceof \stdClass) {
            $merged = get_object_vars($a);
            foreach (get_object_vars($b) as $key => $value) {
                $merged[$key] = isset($merged[$key]) && $merged[$key] instanceof \stdClass && $value instanceof \stdClass ? $this->multiply($merged[$key], $value) : $value;
            }

            return (object) $merged;
        }

        throw VfsError::invalid('jq: '.$this->typeOf($a).' and '.$this->typeOf($b).' cannot be multiplied.');
    }

    private function divide(mixed $a, mixed $b): mixed
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            if ($b == 0) {
                throw VfsError::invalid('jq: division by zero.');
            }
            $result = $a / $b;

            return is_float($result) && floor($result) == $result && abs($result) < PHP_INT_MAX ? (int) $result : $result;
        }
        if (is_string($a) && is_string($b)) {
            return $a === '' ? [] : explode($b, $a);
        }

        throw VfsError::invalid('jq: '.$this->typeOf($a).' and '.$this->typeOf($b).' cannot be divided.');
    }

    private function modulo(mixed $a, mixed $b): int
    {
        if (! (is_int($a) || is_float($a)) || ! (is_int($b) || is_float($b)) || (int) $b === 0) {
            throw VfsError::invalid('jq: % requires two numbers and a non-zero divisor.');
        }

        return (int) $a % (int) $b;
    }

    /**
     * Total order used by sort/comparisons: null < false < true < numbers <
     * strings < arrays < objects.
     */
    private function compare(mixed $a, mixed $b): int
    {
        $rank = fn (mixed $v): int => match (true) {
            $v === null => 0,
            $v === false => 1,
            $v === true => 2,
            is_int($v) || is_float($v) => 3,
            is_string($v) => 4,
            is_array($v) => 5,
            default => 6,
        };
        $ra = $rank($a);
        $rb = $rank($b);
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        if ($ra === 3) {
            return $a <=> $b;
        }
        if ($ra === 4) {
            return strcmp($a, $b) <=> 0;
        }
        if ($ra === 5) {
            foreach ($a as $index => $item) {
                if (! array_key_exists($index, $b)) {
                    return 1;
                }
                $cmp = $this->compare($item, $b[$index]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return count($a) <=> count($b);
        }
        if ($ra === 6) {
            $va = get_object_vars($a);
            $vb = get_object_vars($b);
            $ka = array_map('strval', array_keys($va));
            $kb = array_map('strval', array_keys($vb));
            sort($ka, SORT_STRING);
            sort($kb, SORT_STRING);
            $cmp = $this->compare($ka, $kb);
            if ($cmp !== 0) {
                return $cmp;
            }
            foreach ($ka as $key) {
                $cmp = $this->compare($va[$key], $vb[$key]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
        }

        return 0;
    }

    private function contains(mixed $a, mixed $b): bool
    {
        if (is_string($a) && is_string($b)) {
            return str_contains($a, $b);
        }
        if (is_array($a) && is_array($b)) {
            foreach ($b as $needle) {
                $found = false;
                foreach ($a as $item) {
                    if ($this->contains($item, $needle)) {
                        $found = true;

                        break;
                    }
                }
                if (! $found) {
                    return false;
                }
            }

            return true;
        }
        if ($a instanceof \stdClass && $b instanceof \stdClass) {
            foreach (get_object_vars($b) as $key => $value) {
                if (! property_exists($a, (string) $key) || ! $this->contains($a->{$key}, $value)) {
                    return false;
                }
            }

            return true;
        }

        return $this->compare($a, $b) === 0;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function flatten(array $items, int $depth, int &$count): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_array($item) && $depth > 0) {
                array_push($out, ...$this->flatten($item, $depth - 1, $count));
            } else {
                // $count spans the whole flatten: nested shared arrays cannot
                // multiply past MAX_ITEMS.
                $this->assertItems(++$count, 'flatten');
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Stable sort of input items by a key expression.
     *
     * @param  array<string, mixed>  $keyExpr
     * @return list<array{k: mixed, v: mixed}>
     */
    private function keyed(mixed $input, array $keyExpr, string $name, bool $sort): array
    {
        $pairs = [];
        foreach ($this->requireArray($input, $name) as $index => $item) {
            $keys = $this->eval($keyExpr, $item);
            $pairs[] = ['k' => count($keys) === 1 ? $keys[0] : $keys, 'v' => $item, 'i' => $index];
        }
        if ($sort) {
            usort($pairs, fn (array $a, array $b): int => $this->compare($a['k'], $b['k']) ?: ($a['i'] <=> $b['i']));
        }

        return array_map(fn (array $pair): array => ['k' => $pair['k'], 'v' => $pair['v']], $pairs);
    }

    /**
     * @param  list<array{k: mixed, v: mixed}>  $pairs
     * @return list<mixed>
     */
    private function dedupeSorted(array $pairs): array
    {
        $out = [];
        $last = null;
        foreach ($pairs as $index => $pair) {
            if ($index === 0 || $this->compare($pair['k'], $last) !== 0) {
                $out[] = $pair['v'];
            }
            $last = $pair['k'];
        }

        return $out;
    }

    private function field(mixed $value, string $name, bool $optional): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \stdClass) {
            return property_exists($value, $name) ? $value->{$name} : null;
        }
        if ($optional) {
            return null;
        }

        throw VfsError::invalid("jq: cannot index {$this->typeOf($value)} with \"{$name}\".");
    }

    private function indexValue(mixed $value, mixed $key): mixed
    {
        if (is_string($key)) {
            return $this->field($value, $key, false);
        }
        if (is_int($key) || is_float($key)) {
            if ($value === null) {
                return null;
            }
            if (! is_array($value)) {
                throw VfsError::invalid("jq: cannot index {$this->typeOf($value)} with a number.");
            }
            $index = (int) floor($key);
            if ($index < 0) {
                $index += count($value);
            }

            return $value[$index] ?? null;
        }

        throw VfsError::invalid("jq: cannot index {$this->typeOf($value)} with {$this->typeOf($key)}.");
    }

    private function slice(mixed $value, mixed $from, mixed $to): mixed
    {
        if ($value === null) {
            return null;
        }
        $isString = is_string($value);
        $items = $isString ? mb_str_split($value) : $this->requireArray($value, 'slice');
        $length = count($items);
        $start = $from === null ? 0 : (int) $from;
        $end = $to === null ? $length : (int) $to;
        $start = $start < 0 ? max(0, $length + $start) : min($start, $length);
        $end = $end < 0 ? max(0, $length + $end) : min($end, $length);
        $slice = array_slice($items, $start, max(0, $end - $start));

        return $isString ? implode('', $slice) : $slice;
    }

    /**
     * @return list<mixed>
     */
    private function recurseValues(mixed $value): array
    {
        $out = [$value];
        if (is_array($value) || $value instanceof \stdClass) {
            foreach (is_array($value) ? $value : get_object_vars($value) as $item) {
                $inner = $this->recurseValues($item);
                $this->assertItems(count($out) + count($inner), '..');
                array_push($out, ...$inner);
            }
        }

        return $out;
    }

    /**
     * @param  list<string|array<string, mixed>>  $parts
     * @return list<string>
     */
    private function interpolate(array $parts, mixed $input): array
    {
        $results = [''];
        foreach ($parts as $part) {
            if (is_string($part)) {
                $results = array_map(fn (string $prefix): string => $prefix.$part, $results);

                continue;
            }
            $next = [];
            foreach ($results as $prefix) {
                foreach ($this->eval($part, $input) as $value) {
                    $this->assertItems(count($next) + 1);
                    $text = is_string($value) ? $value : self::encode($value, true, $this->maxBytes);
                    $this->assertBytes(strlen($prefix) + strlen($text), 'string interpolation');
                    $next[] = $prefix.$text;
                }
            }
            $results = $next;
        }

        return $results;
    }

    private function format(string $name, mixed $input): string
    {
        $scalar = fn (mixed $v): string => match (true) {
            $v === null => '',
            is_bool($v) => $v ? 'true' : 'false',
            is_string($v) => $v,
            is_int($v) || is_float($v) => (string) $v,
            default => self::encode($v, true),
        };

        return match ($name) {
            'text' => $scalar($input),
            'json' => self::encode($input, true),
            'csv' => implode(',', array_map(fn (mixed $v): string => is_string($v) ? '"'.str_replace('"', '""', $v).'"' : $scalar($v), $this->requireArray($input, '@csv'))),
            'tsv' => implode("\t", array_map(fn (mixed $v): string => strtr($scalar($v), ['\\' => '\\\\', "\t" => '\\t', "\n" => '\\n', "\r" => '\\r']), $this->requireArray($input, '@tsv'))),
            'base64' => base64_encode($scalar($input)),
            'base64d' => (string) base64_decode($scalar($input)),
            'uri' => rawurlencode($scalar($input)),
            'html' => htmlspecialchars($scalar($input), ENT_QUOTES),
            'sh' => is_array($input)
                ? implode(' ', array_map(fn (mixed $v): string => "'".str_replace("'", "'\\''", $scalar($v))."'", $input))
                : "'".str_replace("'", "'\\''", $scalar($input))."'",
            default => throw VfsError::unsupported("jq format @{$name} is not supported in VFS jq."),
        };
    }

    private function regex(string $pattern, string $flags): string
    {
        $modifiers = '';
        foreach (str_split($flags) as $flag) {
            if (in_array($flag, ['i', 'x', 's', 'm'], true)) {
                $modifiers .= $flag;
            }
        }
        $regex = '~'.str_replace('~', '\\~', $pattern).'~u'.$modifiers;
        if (@preg_match($regex, '') === false) {
            throw VfsError::invalid("jq: invalid regular expression {$pattern}.");
        }

        return $regex;
    }

    private function truthy(mixed $value): bool
    {
        return $value !== null && $value !== false;
    }

    private function typeOf(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) || is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) => 'array',
            default => 'object',
        };
    }

    /**
     * @return list<mixed>
     */
    private function iterate(mixed $input, string $name): array
    {
        if (is_array($input)) {
            return $input;
        }
        if ($input instanceof \stdClass) {
            return array_values(get_object_vars($input));
        }

        throw VfsError::invalid("jq: {$name} requires an array or object, got {$this->typeOf($input)}.");
    }

    /**
     * @return list<mixed>
     */
    private function requireArray(mixed $input, string $name): array
    {
        if (! is_array($input)) {
            throw VfsError::invalid("jq: {$name} requires an array, got {$this->typeOf($input)}.");
        }

        return $input;
    }

    private function requireObject(mixed $input, string $name): \stdClass
    {
        if (! $input instanceof \stdClass) {
            throw VfsError::invalid("jq: {$name} requires an object, got {$this->typeOf($input)}.");
        }

        return $input;
    }

    private function requireString(mixed $input, string $name): string
    {
        if (! is_string($input)) {
            throw VfsError::invalid("jq: {$name} requires a string, got {$this->typeOf($input)}.");
        }

        return $input;
    }

    private function requireNumber(mixed $input, string $name): int|float
    {
        if (! is_int($input) && ! is_float($input)) {
            throw VfsError::invalid("jq: {$name} requires a number, got {$this->typeOf($input)}.");
        }

        return $input;
    }
}
