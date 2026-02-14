<?php

namespace App\Agents\Providers;

use App\Models\IntegrationSetting;
use App\Models\User;
use InvalidArgumentException;

class DynamicProviderResolver
{
    /**
     * Parse a User's brain field and resolve to SDK provider + model.
     *
     * Brain format: "provider:model" (e.g. "glm-coding:glm-4.7", "anthropic:claude-sonnet-4-5-20250929")
     *
     * @return array{provider: string, model: string}
     */
    public function resolve(User $agent): array
    {
        $brain = $agent->brain ?? 'glm-coding:glm-4.7';
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

        // GLM providers use IntegrationSetting for API keys
        if ($this->isGlmProvider($providerKey)) {
            $this->registerGlmProvider($providerKey);
            return ['provider' => $providerKey, 'model' => $model];
        }

        // Standard providers — check DB for API key, fall back to .env
        $sdkProvider = $this->mapToSdkProvider($providerKey);
        if ($sdkProvider) {
            $this->applyIntegrationConfig($providerKey);
            return ['provider' => $sdkProvider, 'model' => $model];
        }

        throw new InvalidArgumentException("Unknown provider: {$providerKey}");
    }

    /**
     * Check if a provider key is a GLM variant.
     */
    private function isGlmProvider(string $providerKey): bool
    {
        return in_array($providerKey, ['glm', 'glm-coding']);
    }

    /**
     * Dynamically register a GLM provider in the Prism config.
     *
     * GLM uses an OpenAI-compatible API, so we register it as an OpenAI provider
     * with a custom URL and API key from IntegrationSetting.
     */
    private function registerGlmProvider(string $providerKey): void
    {
        $integration = IntegrationSetting::where('integration_id', $providerKey)
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

        // Set Prism config for the GLM provider variant (registered via PrismManager::extend)
        config([
            "prism.providers.{$providerKey}" => [
                'api_key' => $apiKey,
                'url' => $url,
            ],
        ]);

        // Register in AI SDK config using our custom driver (registered via AiManager::extend)
        // This routes through GlmPrismGateway → Prism 'glm' provider → chat/completions
        config([
            "ai.providers.{$providerKey}" => [
                'driver' => $providerKey, // 'glm' or 'glm-coding' — custom drivers
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
        ];

        return $map[$providerKey] ?? null;
    }

    /**
     * If a provider has an IntegrationSetting with API key, override prism/ai config.
     * Falls back to .env config silently if no IntegrationSetting exists.
     */
    private function applyIntegrationConfig(string $providerKey): void
    {
        $integration = IntegrationSetting::where('integration_id', $providerKey)
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
    }

    /**
     * Get default URL for a GLM provider.
     */
    private function getDefaultGlmUrl(string $providerKey): string
    {
        return match ($providerKey) {
            'glm' => 'https://open.bigmodel.cn/api/paas/v4',
            'glm-coding' => 'https://api.z.ai/api/coding/paas/v4',
            default => throw new InvalidArgumentException("Unknown GLM provider: {$providerKey}"),
        };
    }

    /**
     * Get the default model for a provider.
     */
    private function getDefaultModel(string $providerKey): string
    {
        // Try DB-stored models first
        $setting = IntegrationSetting::where('integration_id', $providerKey)->first();
        $models = $setting?->getConfigValue('models', []);
        if (is_array($models) && !empty($models)) {
            return array_key_first($models);
        }

        // Hardcoded fallbacks for providers without DB-stored models
        return match ($providerKey) {
            'glm' => 'glm-4-plus',
            'glm-coding' => 'glm-4.7',
            'codex' => 'gpt-5.3-codex',
            'anthropic' => 'claude-sonnet-4-5-20250929',
            'openai' => 'gpt-4o',
            'gemini' => 'gemini-2.0-flash',
            'groq' => 'llama-3.3-70b-versatile',
            'xai' => 'grok-2',
            'deepseek' => 'deepseek-chat',
            'mistral' => 'mistral-large-latest',
            'ollama' => 'llama3.2',
            'openrouter' => 'anthropic/claude-sonnet-4-5-20250929',
            default => 'default',
        };
    }
}
