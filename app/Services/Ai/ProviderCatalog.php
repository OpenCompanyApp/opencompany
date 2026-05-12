<?php

namespace App\Services\Ai;

use App\Models\IntegrationSetting;
use OpenCompany\PrismCodex\CodexTokenStore;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class ProviderCatalog
{
    public function __construct(private RelayRegistry $registry) {}

    public function canonicalProvider(string $provider): ?string
    {
        return $this->registry->canonicalProvider($provider);
    }

    public function hasProvider(string $provider): bool
    {
        return $this->registry->hasProvider($provider);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function provider(string $provider, ?string $workspaceId = null): ?array
    {
        $canonical = $this->canonicalProvider($provider);
        if ($canonical === null) {
            return null;
        }

        $definition = $this->registry->provider($canonical) ?? [];
        $setting = $this->setting($canonical, $workspaceId);

        return array_merge($definition, [
            'id' => $canonical,
            'canonical' => $canonical,
            'driver' => $this->registry->driver($canonical),
            'auth_mode' => $this->registry->authMode($canonical),
            'requires_api_key' => $this->registry->requiresApiKey($canonical),
            'url' => $setting?->getConfigValue('url') ?: config("prism.providers.{$canonical}.url") ?: $this->registry->url($canonical),
            'configured' => $this->configured($canonical, $workspaceId),
            'source' => $setting?->hasValidConfig() ? 'integration' : ($this->configHasCredentials($canonical) ? 'config' : $this->registry->source($canonical)),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?string $workspaceId = null): array
    {
        $providers = [];
        foreach ($this->registry->canonicalProviders() as $provider) {
            $descriptor = $this->provider($provider, $workspaceId);
            if ($descriptor !== null) {
                $providers[] = $descriptor;
            }
        }

        return $providers;
    }

    public function configured(string $provider, ?string $workspaceId = null): bool
    {
        $canonical = $this->canonicalProvider($provider) ?? $provider;

        if ($this->registry->authMode($canonical) === 'oauth') {
            if ($canonical === 'codex') {
                $token = CodexTokenStore::current();

                return $token !== null && ! $token->isExpired();
            }

            return false;
        }

        $setting = $this->setting($canonical, $workspaceId);
        if ($setting?->hasValidConfig()) {
            return true;
        }

        if ($this->configHasCredentials($canonical)) {
            return true;
        }

        if (! $this->registry->requiresApiKey($canonical)) {
            return filled(config("prism.providers.{$canonical}.url") ?: config("ai.providers.{$canonical}.url") ?: $this->registry->url($canonical));
        }

        return false;
    }

    private function configHasCredentials(string $provider): bool
    {
        return filled(config("ai.providers.{$provider}.key"))
            || filled(config("prism.providers.{$provider}.api_key"));
    }

    private function setting(string $provider, ?string $workspaceId): ?IntegrationSetting
    {
        $query = IntegrationSetting::query()
            ->where('integration_id', $provider)
            ->default();

        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        } elseif (app()->bound('currentWorkspace')) {
            $query->forWorkspace();
        }

        return $query->first();
    }
}
