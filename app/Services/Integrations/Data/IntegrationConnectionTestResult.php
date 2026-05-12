<?php

namespace App\Services\Integrations\Data;

class IntegrationConnectionTestResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly ?string $error = null,
        public readonly int $status = 200,
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(array_merge([
            'success' => $this->success,
            'message' => $this->message,
            'error' => $this->error,
        ], $this->meta), static fn ($value) => $value !== null);
    }
}
