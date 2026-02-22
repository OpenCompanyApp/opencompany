<?php

namespace App\Services;

class LuaResult
{
    public function __construct(
        public readonly string $output,
        public readonly ?string $error,
        public readonly mixed $result,
        public readonly float $executionTime,
        public readonly ?int $memoryUsage,
    ) {}

    public function succeeded(): bool
    {
        return $this->error === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'output' => $this->output,
            'error' => $this->error,
            'result' => $this->result,
            'executionTime' => $this->executionTime,
            'memoryUsage' => $this->memoryUsage,
        ];
    }
}
