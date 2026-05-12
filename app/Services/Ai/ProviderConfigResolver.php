<?php

namespace App\Services\Ai;

use App\Agents\OpenCompanyAgent;
use App\Models\IntegrationSetting;
use Laravel\Ai\AiManager;

/**
 * Materializes workspace provider settings into Laravel AI and Prism config.
 *
 * The provider catalog answers what exists; this resolver answers whether a
 * provider is usable for the current workspace and mutates runtime config only
 * after that check. Keep credential lookup and manager purging here so model
 * execution paths do not duplicate provider-specific setup.
 */
class ProviderConfigResolver
{
    public function __construct(private ProviderCatalog $catalog) {}

    /**
     * @return array{provider: string, model: string}
     */
    public function resolve(string $providerKey, string $model, ?string $workspaceId = null): array
    {
        $providerKey = $this->catalog->canonicalProvider($providerKey) ?? $providerKey;

        if (! $this->catalog->hasProvider($providerKey)) {
            throw new \InvalidArgumentException("Unknown provider: {$providerKey}");
        }

        $provider = $this->catalog->provider($providerKey, $workspaceId) ?? [];

        if (($provider['auth_mode'] ?? null) === 'oauth') {
            // Codex/OpenCode style providers do not use a static API key from
            // workspace integration settings. Register the SDK driver with a
            // sentinel key so the OAuth-backed gateway can own authentication.
            $this->registerCodexProvider();

            return ['provider' => $providerKey, 'model' => $model];
        }

        if (app()->environment('testing')
            && class_exists(OpenCompanyAgent::class)
            && OpenCompanyAgent::isFaked()) {
            // Fake-agent tests assert orchestration without registering real
            // provider credentials. Do not force integration setup when the
            // agent class has explicitly opted out of network calls.
            return ['provider' => $providerKey, 'model' => $model];
        }

        if (app()->environment('testing') && ! in_array($providerKey, ['z', 'z-api'], true)) {
            $this->registerProviderConfig($providerKey, $workspaceId);

            return ['provider' => $providerKey, 'model' => $model];
        }

        if (! $this->catalog->configured($providerKey, $workspaceId)) {
            throw new \InvalidArgumentException(
                "AI provider '{$providerKey}' is not configured. Please enable it in Integrations settings."
            );
        }

        $this->registerProviderConfig($providerKey, $workspaceId);

        return ['provider' => $providerKey, 'model' => $model];
    }

    private function registerProviderConfig(string $providerKey, ?string $workspaceId): void
    {
        $setting = $this->setting($providerKey, $workspaceId);
        $provider = $this->catalog->provider($providerKey, $workspaceId) ?? [];

        $apiKey = $setting?->getConfigValue('api_key') ?: config("prism.providers.{$providerKey}.api_key") ?: config("ai.providers.{$providerKey}.key");
        $url = $setting?->getConfigValue('url') ?: ($provider['url'] ?? null);

        // Laravel AI and Prism read provider config from separate namespaces in
        // this app. Keep both synchronized until all provider execution has
        // moved fully behind the Laravel AI SDK.
        config(["ai.providers.{$providerKey}" => array_merge(
            config("ai.providers.{$providerKey}", []),
            array_filter([
                'driver' => $providerKey,
                'key' => $apiKey,
                'url' => $url,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        )]);

        config(["prism.providers.{$providerKey}" => array_merge(
            config("prism.providers.{$providerKey}", []),
            array_filter([
                'api_key' => $apiKey,
                'url' => $url,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        )]);

        if (app()->bound(AiManager::class)) {
            // AiManager caches provider instances. Purge after changing config
            // or a queue worker can keep using stale keys/URLs for later runs.
            app(AiManager::class)->purge($providerKey);
        }
    }

    private function registerCodexProvider(): void
    {
        config([
            'ai.providers.codex' => [
                'driver' => 'codex',
                'key' => 'codex-oauth',
            ],
        ]);

        if (app()->bound(AiManager::class)) {
            app(AiManager::class)->purge('codex');
        }
    }

    private function setting(string $provider, ?string $workspaceId): ?IntegrationSetting
    {
        $query = IntegrationSetting::query()
            ->where('integration_id', $provider)
            ->where('enabled', true)
            ->default();

        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        } elseif (app()->bound('currentWorkspace')) {
            // HTTP requests usually rely on ResolveWorkspace. CLI/live-test
            // callers can pass an explicit workspace ID instead.
            $query->forWorkspace();
        }

        return $query->first();
    }
}
