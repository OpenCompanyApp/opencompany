<?php

namespace App\Services\Ai;

use App\Models\IntegrationSetting;
use OpenCompany\PrismCodex\CodexTokenStore;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

/**
 * Workspace-aware view over the prism-relay provider registry.
 *
 * The sibling package owns provider definitions and aliases. OpenCompany adds
 * workspace configuration status, configured URLs, and OAuth token checks so UI
 * and runtime code can ask one catalog what is available right now.
 */
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

        // Registry metadata is the source of truth, but workspace settings can
        // override credentials and URLs. Keep the merged descriptor explicit so
        // callers can tell whether a value came from settings, config, or the
        // package registry.
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
                // Codex uses a token store instead of IntegrationSetting API
                // keys. Treat expired tokens as unconfigured so the UI prompts
                // for reconnect before a runtime call fails.
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
