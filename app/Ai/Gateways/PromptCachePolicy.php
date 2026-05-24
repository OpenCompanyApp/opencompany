<?php

namespace App\Ai\Gateways;

use App\Domain\Ai\Catalog\AiCatalog;

/**
 * Provides provider-specific prompt-cache options for Laravel AI requests.
 *
 * Cache behavior is catalog metadata now. That keeps OpenCompany independent
 * from package-owned cache helpers while preserving the invariant that runtime
 * calls only receive options a provider explicitly supports.
 */
class PromptCachePolicy
{
    public function __construct(
        private readonly ?AiCatalog $catalog = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(string $provider): array
    {
        return $this->catalog()->provider($provider)?->cacheOptions ?? [];
    }

    public function shouldWrap(string $provider): bool
    {
        return true;
    }

    private function catalog(): AiCatalog
    {
        return $this->catalog ?? app(AiCatalog::class);
    }
}
