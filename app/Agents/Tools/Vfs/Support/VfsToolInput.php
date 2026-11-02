<?php

namespace App\Agents\Tools\Vfs\Support;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Spec\VfsOperationSpec;
use App\Domain\Vfs\Spec\VfsParameterSpec;

/**
 * Reads tool arguments the way the operation spec declares them.
 *
 * Resolution order per parameter: canonical key, then declared aliases, then
 * the snake_case and camelCase spellings (direct callers may send either;
 * Code Mode converts snake_case keys to camelCase), then the spec default. Integers are validated strictly (no
 * silent casts of "10abc") and fail with invalid_request naming the field, so
 * a typo never becomes an unbounded or zero budget.
 */
final class VfsToolInput
{
    /**
     * @param  \ArrayAccess<string, mixed>|array<string, mixed>  $request
     */
    public function __construct(
        private readonly VfsOperationSpec $spec,
        private readonly \ArrayAccess|array $request,
    ) {}

    public function has(string $name): bool
    {
        return $this->raw($name) !== null;
    }

    public function raw(string $name): mixed
    {
        $parameter = $this->spec->parameter($name);
        $keys = [$name, ...($parameter->aliases ?? []), self::snake($name), self::camel($name)];
        foreach (array_unique($keys) as $key) {
            if (isset($this->request[$key])) {
                return $this->request[$key];
            }
        }

        return null;
    }

    public function string(string $name): string
    {
        $value = $this->raw($name) ?? $this->spec->parameter($name)->default;

        return is_scalar($value) ? (string) $value : '';
    }

    public function optionalString(string $name): ?string
    {
        $value = $this->raw($name);
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_scalar($value)) {
            throw VfsError::invalid("{$name} must be a string.", ['field' => $name]);
        }

        return (string) $value;
    }

    public function bool(string $name): bool
    {
        $value = $this->raw($name) ?? $this->spec->parameter($name)->default ?? false;
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'yes', 'on'], true);
    }

    public function int(string $name, int $min = 0, ?int $max = null): int
    {
        $parameter = $this->spec->parameter($name);
        $raw = $this->raw($name);
        if ($raw === null) {
            return (int) ($parameter->default ?? $min);
        }
        if (! is_int($raw) && (! is_string($raw) || preg_match('/^\d+$/', $raw) !== 1)) {
            throw VfsError::invalid("{$name} must be an integer.", ['field' => $name]);
        }
        $value = (int) $raw;
        if ($value < $min) {
            throw VfsError::invalid("{$name} must be at least {$min}.", ['field' => $name, 'min' => $min]);
        }

        return $max === null ? $value : min($value, $max);
    }

    /**
     * Enum parameter: rejects values outside the declared enum.
     */
    public function choice(string $name): string
    {
        $parameter = $this->spec->parameter($name);
        $value = $this->string($name);
        if ($parameter instanceof VfsParameterSpec && $parameter->enum !== [] && ! in_array($value, $parameter->enum, true)) {
            throw VfsError::invalid("{$name} must be one of: ".implode(', ', $parameter->enum).'.', [
                'field' => $name,
                'allowed' => $parameter->enum,
            ]);
        }

        return $value;
    }

    /**
     * Budget from whichever budget parameters the spec declares; undeclared
     * ones keep VfsBudget defaults. `limit` maps to maxEntries for listing
     * helpers.
     */
    public function budget(): VfsBudget
    {
        $value = fn (string $name, int $default, int $min): int => $this->spec->parameter($name) !== null
            ? $this->int($name, $min)
            : $default;

        return new VfsBudget(
            maxEntries: $this->spec->parameter('limit') !== null ? $this->int('limit', 1) : $value('maxEntries', 100, 1),
            maxDepth: $value('maxDepth', 3, 0),
            maxBytes: $value('maxBytes', 100_000, 1),
            maxFiles: $value('maxFiles', 100, 1),
            maxMatches: $value('maxMatches', 100, 1),
            maxLineLength: $value('maxLineLength', 2_000, 1),
        );
    }

    public function cursor(): ?string
    {
        $cursor = $this->raw('cursor');
        if ($cursor === null || $cursor === '') {
            return null;
        }
        if (! is_string($cursor)) {
            throw VfsError::invalid('cursor must be the opaque next_cursor string from a previous result.', ['field' => 'cursor']);
        }

        return $cursor;
    }

    /** Code Mode converts snake_case keys to camelCase before dispatch. */
    private static function camel(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }

    private static function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
