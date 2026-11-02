<?php

namespace App\Agents\Tools\External;

/**
 * Value object returned by Tool::execute().
 *
 * Encapsulates both successful results and errors in a structured way,
 * replacing the convention of returning raw strings or JSON-encoded data.
 */
class ToolResult
{
    /**
     * @param  mixed        $data   Result data — string, array, or any serializable value
     * @param  string|null  $error  Error message if the tool failed (null = success)
     * @param  array<string, mixed>  $meta   Optional metadata (files created, attachments, timing, etc.)
     */
    public function __construct(
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly array $meta = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data, array $meta = []): self
    {
        return new self(data: $data, meta: $meta);
    }

    /**
     * Create an error result.
     */
    public static function error(string $message): self
    {
        return new self(data: null, error: $message);
    }

    /**
     * Whether the tool execution succeeded.
     */
    public function succeeded(): bool
    {
        return $this->error === null;
    }

    /**
     * Get the data as a string for backward-compatible consumers.
     */
    public function toString(): string
    {
        if ($this->error !== null) {
            return "Error: {$this->error}";
        }

        if (is_string($this->data)) {
            return $this->data;
        }

        if (is_array($this->data)) {
            return json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        return (string) ($this->data ?? '');
    }
}
