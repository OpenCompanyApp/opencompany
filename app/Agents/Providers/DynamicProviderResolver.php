<?php

namespace App\Agents\Providers;

use App\Models\IntegrationSetting;
use App\Models\User;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use OpenCompany\PrismRelay\Registry\RelayRegistry;

class DynamicProviderResolver
{
    private ?string $workspaceId = null;

    public function __construct(
        private ?RelayRegistry $registry = null,
    ) {}

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
        $providerKey = $this->registry()->canonicalProvider($providerKey) ?? $providerKey;

        if (! $this->registry()->hasProvider($providerKey)) {
            throw new InvalidArgumentException("Unknown provider: {$providerKey}");
        }

        // Codex uses ChatGPT subscription OAuth, not workspace API keys.
        if ($this->registry()->authMode($providerKey) === 'oauth') {
            $this->registerCodexProvider();
            return ['provider' => $providerKey, 'model' => $model];
        }

        $this->applyProviderConfig($providerKey);

        return ['provider' => $providerKey, 'model' => $model];
    }

    /**
     * Dynamically register a workspace-configured provider in Laravel AI config.
     */
    private function applyProviderConfig(string $providerKey): void
    {
        $integration = IntegrationSetting::where('workspace_id', $this->workspaceId)
            ->where('integration_id', $providerKey)
            ->where('enabled', true)
            ->first();

        if (! $integration || ! $integration->hasValidConfig()) {
            if ($this->canUseConfiguredProvider($providerKey)) {
                $this->registerAiProviderConfig($providerKey, []);
                $this->registerPrismProviderConfig($providerKey, []);
                $this->purgeAiProvider($providerKey);

                return;
            }

            throw new InvalidArgumentException(
                "AI provider '{$providerKey}' is not configured. Please enable it in Integrations settings."
            );
        }

        $apiKey = $integration->getConfigValue('api_key');
        $url = $integration->getConfigValue('url') ?? $this->getDefaultUrl($providerKey);

        $this->registerAiProviderConfig($providerKey, array_filter([
            'key' => $apiKey,
            'url' => $url,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        $this->registerPrismProviderConfig($providerKey, array_filter([
            'api_key' => $apiKey,
            'url' => $url,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        $this->purgeAiProvider($providerKey);
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

        $this->purgeAiProvider('codex');
    }

    /**
     * Check whether .env/config already provides this provider.
     */
    private function canUseConfiguredProvider(string $providerKey): bool
    {
        $configured = config("ai.providers.{$providerKey}");

        if (is_array($configured) && array_key_exists('key', $configured) && filled($configured['key'])) {
            return true;
        }

        if (filled(config("prism.providers.{$providerKey}.api_key"))) {
            return true;
        }

        if ($providerKey === 'ollama' && filled(config('prism.providers.ollama.url'))) {
            return true;
        }

        return in_array($providerKey, [
            'anthropic',
            'openai',
            'gemini',
            'groq',
            'xai',
            'openrouter',
            'deepseek',
            'mistral',
            'ollama',
            'perplexity',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function registerAiProviderConfig(string $providerKey, array $overrides): void
    {
        $fallbackKey = config("ai.providers.{$providerKey}.key")
            ?: config("prism.providers.{$providerKey}.api_key");

        config(["ai.providers.{$providerKey}" => array_merge(
            config("ai.providers.{$providerKey}", []),
            array_filter([
                'driver' => $providerKey,
                'key' => $fallbackKey,
                'url' => $this->getDefaultUrl($providerKey),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            $overrides,
        )]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function registerPrismProviderConfig(string $providerKey, array $overrides): void
    {
        config(["prism.providers.{$providerKey}" => array_merge(
            config("prism.providers.{$providerKey}", []),
            array_filter([
                'api_key' => config("prism.providers.{$providerKey}.api_key")
                    ?: config("ai.providers.{$providerKey}.key"),
                'url' => $this->getDefaultUrl($providerKey),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            $overrides,
        )]);
    }

    private function purgeAiProvider(string $providerKey): void
    {
        if (app()->bound(AiManager::class)) {
            app(AiManager::class)->purge($providerKey);
        }
    }

    /**
     * Get default URL for a known provider.
     */
    private function getDefaultUrl(string $providerKey): ?string
    {
        return $this->registry()->url($providerKey) ?: null;
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

        return (string) ($this->registry()->provider($providerKey)['default_model'] ?? 'default');
    }

    private function registry(): RelayRegistry
    {
        return $this->registry ??= app(RelayRegistry::class);
    }
}
