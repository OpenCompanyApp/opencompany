<?php

namespace App\Ai\Gateways;

use OpenCompany\PrismRelay\Caching\CacheStrategy;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class PromptCachePolicy
{
    public function __construct(
        private readonly ?RelayRegistry $registry = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(string $provider): array
    {
        $provider = $this->canonicalProvider($provider);

        return CacheStrategy::providerOptions($provider);
    }

    public function shouldWrap(string $provider): bool
    {
        return true;
    }

    private function canonicalProvider(string $provider): string
    {
        $registry = $this->registry ?? (app()->bound(RelayRegistry::class) ? app(RelayRegistry::class) : null);

        return $registry?->canonicalProvider($provider) ?? $provider;
    }
}
