<?php

namespace App\Domain\Ai\Usage;

/**
 * Request-local buffer for raw OpenRouter generation payloads.
 *
 * Laravel AI normalizes responses and drops provider generation IDs. The
 * OpenRouter gateway records the raw response here so the usage recorder can
 * attach exact billing metadata to the immediately following ledger row.
 */
class OpenRouterGenerationStore
{
    /** @var list<array<string, mixed>> */
    private array $generations = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function push(array $payload): void
    {
        $this->generations[] = $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pullLatest(): ?array
    {
        return array_pop($this->generations);
    }
}
