<?php

namespace App\Domain\Ai\Catalog;

/**
 * App-owned provider/model catalog.
 *
 * The catalog enriches runtime decisions with defaults, pricing, context windows,
 * aliases, and cache capability. It intentionally does not decide whether a
 * brand-new model ID is valid for a provider; provider APIs are the final source
 * of truth for model availability.
 */
class AiCatalog
{
    /** @var array<string, ProviderInfo> */
    private array $providers;

    /** @var array<string, string> */
    private array $aliases = [];

    public function __construct()
    {
        $this->providers = $this->loadProviders();
        $this->aliases = $this->buildAliases();
    }

    public function canonicalProvider(string $provider): ?string
    {
        $normalized = strtolower(trim($provider));

        if (isset($this->providers[$normalized])) {
            return $normalized;
        }

        return $this->aliases[$normalized] ?? null;
    }

    public function hasProvider(string $provider): bool
    {
        return $this->canonicalProvider($provider) !== null;
    }

    public function provider(string $provider): ?ProviderInfo
    {
        $canonical = $this->canonicalProvider($provider);

        return $canonical !== null ? $this->providers[$canonical] : null;
    }

    /**
     * @return list<ProviderInfo>
     */
    public function providers(): array
    {
        return array_values($this->providers);
    }

    public function model(string $provider, string $model): ?ModelInfo
    {
        return $this->provider($provider)?->model($model);
    }

    public function defaultModel(string $provider): string
    {
        $info = $this->provider($provider);

        return $info?->defaultModel ?? 'default';
    }

    /**
     * @return list<string>
     */
    public function registrationNames(): array
    {
        $names = array_keys($this->providers);

        foreach ($this->aliases as $alias => $_provider) {
            $names[] = $alias;
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, ProviderInfo>
     */
    private function loadProviders(): array
    {
        $providers = require app_path('Domain/Ai/Catalog/generated/providers.php');
        $overridesPath = app_path('Domain/Ai/Catalog/overrides/providers.php');
        $overrides = is_file($overridesPath) ? require $overridesPath : [];
        $merged = array_replace_recursive($providers, is_array($overrides) ? $overrides : []);
        $result = [];

        foreach ($merged as $id => $provider) {
            if (is_array($provider)) {
                $result[strtolower((string) $id)] = ProviderInfo::fromArray(strtolower((string) $id), $provider);
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function buildAliases(): array
    {
        $aliases = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->aliases as $alias) {
                $aliases[strtolower($alias)] = $provider->id;
            }
        }

        return $aliases;
    }
}
