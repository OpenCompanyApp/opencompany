<?php

namespace App\Ai\TextGeneration;

use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\TextGenerationOptions;

class MergedTextGenerationOptions extends TextGenerationOptions
{
    /**
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        private readonly ?TextGenerationOptions $inner,
        private readonly array $providerOptions,
    ) {
        parent::__construct(
            maxSteps: $inner?->maxSteps,
            maxTokens: $inner?->maxTokens,
            temperature: $inner?->temperature,
            agent: $inner?->agent,
            topP: $inner?->topP,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function providerOptions(Lab|string $provider): ?array
    {
        $key = $provider instanceof Lab ? $provider->value : $provider;
        $existing = $this->inner?->providerOptions($provider) ?? [];
        $additional = $this->providerOptions[$key] ?? [];

        return array_filter(array_merge($existing, $additional), static fn (mixed $value): bool => $value !== null);
    }
}
