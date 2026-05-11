<?php

namespace App\Agents\Providers;

use App\Models\IntegrationSetting;
use App\Models\User;
use InvalidArgumentException;
use OpenCompany\PrismRelay\Meta\ProviderMeta;
use OpenCompany\PrismRelay\RelayManager;

class DynamicProviderResolver
{
    private ?string $workspaceId = null;

    /**
     * Set the workspace ID for scoping IntegrationSetting queries.
     * Use this when calling resolveFromParts() directly without resolve(User).
     */
    public function setWorkspaceId(?string $workspaceId): self
    {
        $this->workspaceId = $workspaceId;

        return $this;
    }

    /**
     * Parse a User's brain field and resolve to SDK provider + model.
     *
     * Brain format: "provider:model" (e.g. "z:glm-5.1", "anthropic:claude-sonnet-4-5-20250929")
     *
     * @return array{provider: string, model: string}
     */
    public function resolve(User $agent): array
    {
        $this->workspaceId = $agent->workspace_id;

        $brain = $agent->brain ?? 'z:glm-5.1';
        $parts = explode(':', $brain, 2);
        $providerKey = $parts[0];
        $model = $parts[1] ?? $this->getDefaultModel($providerKey);

        return $this->resolveFromParts($providerKey, $model);
    }

    /**
     * Resolve a provider key + model string to SDK provider + model.
     *
     * @return array{provider: string, model: string}
     */
    public function resolveFromParts(string $providerKey, string $model): array
    {
        // Codex uses ChatGPT subscription OAuth — no API key needed
        if ($providerKey === 'codex') {
            $this->registerCodexProvider();
            return ['provider' => 'codex', 'model' => $model];
        }

        // Standard providers — check DB for API key, fall back to .env
        $sdkProvider = $this->mapToSdkProvider($providerKey);
        if ($sdkProvider) {
            $this->applyIntegrationConfig($providerKey);
            return ['provider' => $sdkProvider, 'model' => $model];
        }

        // Custom providers use IntegrationSetting for API keys
        if ($this->isRelayBackedProvider($providerKey)) {
            $this->registerGlmProvider($providerKey);
            return ['provider' => $providerKey, 'model' => $model];
        }

        throw new InvalidArgumentException("Unknown provider: {$providerKey}");
    }

    /**
     * Check if a provider key is managed by Prism Relay.
     */
    private function isRelayBackedProvider(string $providerKey): bool
    {
        return ! $this->mapToSdkProvider($providerKey)
            && (new RelayManager)->isRelayProvider($providerKey);
    }

    /**
     * Dynamically register a custom provider in the Prism config.
     */
    private function registerGlmProvider(string $providerKey): void
    {
        $integration = IntegrationSetting::where('workspace_id', $this->workspaceId)
            ->where('integration_id', $providerKey)
            ->where('enabled', true)
            ->first();

        if (!$integration) {
            throw new InvalidArgumentException(
                "AI provider '{$providerKey}' is not configured. Please enable it in Integrations settings."
            );
        }

        if (!$integration->hasValidConfig()) {
            throw new InvalidArgumentException(
                "AI provider '{$providerKey}' is not properly configured. Please check the API settings."
            );
        }

        $apiKey = $integration->getConfigValue('api_key');
        $url = $integration->getConfigValue('url') ?? $this->getDefaultGlmUrl($providerKey);

        // Set Prism config for the provider variant (registered via PrismManager::extend)
        config([
            "prism.providers.{$providerKey}" => [
                'api_key' => $apiKey,
                'url' => $url,
            ],
        ]);

        // Register in AI SDK config using our custom driver (registered via AiManager::extend).
        // This routes through prism-relay's Laravel AI TextGateway adapter.
        config([
            "ai.providers.{$providerKey}" => [
                'driver' => $providerKey,
                'key' => $apiKey,
            ],
        ]);
    }

    /**
     * Register the Codex provider in AI SDK config.
     * Codex uses OAuth tokens managed by the prism-codex package.
     */
    private function registerCodexProvider(): void
    {
        config([
            'ai.providers.codex' => [
                'driver' => 'codex',
                'key' => 'codex-oauth',
            ],
        ]);
    }

    /**
     * Map a brain provider key to an AI SDK provider name.
     */
    private function mapToSdkProvider(string $providerKey): ?string
    {
        $map = [
            'anthropic' => 'anthropic',
            'openai' => 'openai',
            'gemini' => 'gemini',
            'groq' => 'groq',
            'xai' => 'xai',
            'openrouter' => 'openrouter',
            'deepseek' => 'deepseek',
            'mistral' => 'mistral',
            'ollama' => 'ollama',
            'perplexity' => 'perplexity',
        ];

        return $map[$providerKey] ?? null;
    }

    /**
     * If a provider has an IntegrationSetting with API key, override prism/ai config.
     * Falls back to .env config silently if no IntegrationSetting exists.
     */
    private function applyIntegrationConfig(string $providerKey): void
    {
        $integration = IntegrationSetting::where('workspace_id', $this->workspaceId)
            ->where('integration_id', $providerKey)
            ->where('enabled', true)
            ->first();

        if (!$integration || !$integration->hasValidConfig()) {
            return; // Fall back to .env config
        }

        $apiKey = $integration->getConfigValue('api_key');
        $url = $integration->getConfigValue('url');

        $config = ['api_key' => $apiKey];
        if ($url) {
            $config['url'] = $url;
        }

        // Merge into existing prism config (preserves .env values for unset fields)
        config(["prism.providers.{$providerKey}" => array_merge(
            config("prism.providers.{$providerKey}", []),
            $config,
        )]);

        $sdkProvider = $this->mapToSdkProvider($providerKey) ?? $providerKey;
        $aiConfig = ['driver' => $sdkProvider, 'key' => $apiKey];
        if ($url) {
            $aiConfig['url'] = $url;
        }

        // Laravel AI native 0.6 gateways read from ai.providers.*, while
        // prism-relay and KosmoKrator-facing code still read prism.providers.*.
        config(["ai.providers.{$providerKey}" => array_merge(
            config("ai.providers.{$providerKey}", []),
            $aiConfig,
        )]);
    }

    /**
     * Get default URL for a known provider.
     */
    private function getDefaultGlmUrl(string $providerKey): string
    {
        return (new ProviderMeta)->url($providerKey)
            ?? throw new InvalidArgumentException("Unknown custom provider: {$providerKey}");
    }

    /**
     * Get the default model for a provider.
     */
    private function getDefaultModel(string $providerKey): string
    {
        // Try DB-stored models first
        $setting = IntegrationSetting::where('workspace_id', $this->workspaceId)
            ->where('integration_id', $providerKey)->first();
        $models = $setting?->getConfigValue('models', []);
        if (is_array($models) && !empty($models)) {
            return array_key_first($models);
        }

        // Fall back to prism-relay's provider metadata registry
        return (new ProviderMeta)->defaultModel($providerKey) ?? 'default';
    }
}
