<?php

namespace App\Domain\Vfs\Core;

/**
 * Parsed argv for one VFS command, driven by that command's VfsCommandSpec.
 *
 * Owns the GNU-style option grammar shared by every command: clustered short
 * flags (`-la`), short value options with attached or separate values (`-n5`,
 * `-n 5`, `-d,`), long flags, `--long=value`, long aliases declared in the spec
 * (`--ignore-case` -> `-i`), numeric shorthand (`head -5`), options after
 * operands, and `--` ending option parsing.
 *
 * Invariant: parsing uses only the command's own spec. The previous parser
 * consulted the union of every command's value options, which is how
 * `paste -d , a b` read `,` as a path and `cmp -n 10 a b` read `10` as one.
 *
 * A word whose first character was quoted (VfsShellCommand::$literal) is
 * always an operand, so `grep "-v" f` searches for "-v".
 *
 * Unknown flags fail with `unsupported` and the full supported flag list so
 * every command reports the same, actionable shape.
 */
final class VfsArgs
{
    /**
     * @param  array<string, true>  $flags  Present short flags (`l`) and long flags (`--hidden`).
     * @param  array<string, list<string>>  $values  Value option name (as spelled in the spec) => values in order.
     * @param  list<string>  $operands
     * @param  list<bool>  $operandLiteral
     * @param  list<bool>  $operandGlob
     */
    public function __construct(
        public readonly string $command,
        public readonly array $flags,
        public readonly array $values,
        public readonly array $operands,
        public readonly array $operandLiteral,
        public readonly array $operandGlob,
        public readonly ?string $numeric = null,
    ) {}

    /**
     * @param  list<string>  $words  argv without the command name
     * @param  list<bool>  $literal
     * @param  list<bool>  $glob
     */
    public static function parse(VfsCommandSpec $spec, array $words, array $literal = [], array $glob = []): self
    {
        $flags = [];
        $values = [];
        $operands = [];
        $operandLiteral = [];
        $operandGlob = [];
        $numeric = null;
        $optionsDone = false;
        $shortValue = [];
        $longValue = [];
        foreach ($spec->valueOptions as $option) {
            if (preg_match('/^-[A-Za-z0-9]$/', $option) === 1) {
                $shortValue[$option[1]] = $option;
            } else {
                $longValue[$option] = $option;
            }
        }
        $longFlags = array_fill_keys($spec->longFlags, true);
        $shortFlags = array_fill_keys($spec->shortFlags, true);

        $count = count($words);
        for ($i = 0; $i < $count; $i++) {
            $word = $words[$i];
            $isLiteral = $literal[$i] ?? false;
            if ($optionsDone || $isLiteral || $word === '-' || ! str_starts_with($word, '-')) {
                $operands[] = $word;
                $operandLiteral[] = $isLiteral;
                $operandGlob[] = $glob[$i] ?? false;

                continue;
            }
            if ($word === '--') {
                $optionsDone = true;

                continue;
            }

            // Spec-declared long aliases map onto canonical options first.
            $name = $word;
            $inline = null;
            if (str_starts_with($word, '--') && str_contains($word, '=')) {
                [$name, $inline] = explode('=', $word, 2);
            }
            $aliased = false;
            if (isset($spec->longAliases[$name])) {
                $name = $spec->longAliases[$name];
                $aliased = true;
                if ($inline === null && strlen($name) === 2 && isset($shortFlags[$name[1]])) {
                    $flags[$name[1]] = true;

                    continue;
                }
            }

            if (isset($longValue[$name]) || ($aliased && strlen($name) === 2 && isset($shortValue[$name[1]]))) {
                $value = $inline;
                if ($value === null) {
                    if (! array_key_exists($i + 1, $words)) {
                        throw self::missingValue($spec, $name);
                    }
                    $value = $words[++$i];
                }
                $values[$name][] = $value;

                continue;
            }
            if (isset($longFlags[$name]) && $inline === null) {
                $flags[$name] = true;

                continue;
            }
            if ($spec->numericShorthand && preg_match('/^-(\d+)([A-Za-z]?)$/', $word, $matches) === 1) {
                $numeric = $matches[1].$matches[2];

                continue;
            }
            if (str_starts_with($word, '--') || $inline !== null) {
                throw self::unsupportedFlag($spec, $word);
            }
            // Short cluster: flags until a value option swallows the rest.
            $letters = substr($word, 1);
            $length = strlen($letters);
            for ($j = 0; $j < $length; $j++) {
                $letter = $letters[$j];
                if (isset($shortValue[$letter])) {
                    $rest = substr($letters, $j + 1);
                    if ($rest === '') {
                        if (! array_key_exists($i + 1, $words)) {
                            throw self::missingValue($spec, '-'.$letter);
                        }
                        $rest = $words[++$i];
                    }
                    $values['-'.$letter][] = $rest;

                    continue 2;
                }
                if (! isset($shortFlags[$letter])) {
                    throw self::unsupportedFlag($spec, '-'.$letter);
                }
                $flags[$letter] = true;
            }
        }

        return new self($spec->name, $flags, $values, $operands, $operandLiteral, $operandGlob, $numeric);
    }

    /**
     * Whether any of the given flags is present. Accepts `l`, `-l`, or `--hidden`.
     */
    public function has(string ...$names): bool
    {
        foreach ($names as $name) {
            $key = strlen($name) === 2 && $name[0] === '-' ? $name[1] : $name;
            if (isset($this->flags[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The last value supplied for any of the given option names.
     *
     * @param  string|list<string>  $names
     */
    public function value(string|array $names): ?string
    {
        $all = $this->values($names);

        return $all === [] ? null : $all[count($all) - 1];
    }

    /**
     * Every value supplied for the given option names, in argv order.
     *
     * @param  string|list<string>  $names
     * @return list<string>
     */
    public function values(string|array $names): array
    {
        $out = [];
        foreach ((array) $names as $name) {
            foreach ($this->values[$name] ?? [] as $value) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  string|list<string>  $names
     */
    public function provided(string|array $names): bool
    {
        return $this->values($names) !== [];
    }

    /**
     * Integer option value or null when absent.
     *
     * @param  string|list<string>  $names
     *
     * @throws VfsError invalid_request when the value is not a non-negative integer
     */
    public function int(string|array $names, ?int $min = 0): ?int
    {
        $value = $this->value($names);
        if ($value === null) {
            return null;
        }
        if (preg_match('/^\d+$/', $value) !== 1 || ($min !== null && (int) $value < $min)) {
            $name = is_array($names) ? $names[0] : $names;
            throw VfsError::invalid("{$this->command} {$name} requires an integer value".($min !== null && $min > 0 ? " of at least {$min}" : '').", got \"{$value}\".", [
                'command' => $this->command,
                'option' => $name,
            ]);
        }

        return (int) $value;
    }

    public static function unsupportedFlag(VfsCommandSpec $spec, string $flag): VfsError
    {
        return VfsError::unsupported("Unsupported {$spec->name} flag: {$flag}", [
            'command' => $spec->name,
            'flag' => $flag,
            'supported_flags' => $spec->supportedFlagList(),
            'hint' => "Run `help {$spec->name}` for the supported flags and their VFS semantics.",
        ]);
    }

    private static function missingValue(VfsCommandSpec $spec, string $option): VfsError
    {
        return VfsError::invalid("{$spec->name} {$option} requires a value.", [
            'command' => $spec->name,
            'option' => $option,
        ]);
    }
}
