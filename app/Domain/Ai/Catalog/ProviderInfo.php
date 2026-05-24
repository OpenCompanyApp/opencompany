<?php

namespace App\Domain\Ai\Catalog;

/**
 * Catalog metadata for an AI provider supported by OpenCompany.
 *
 * Providers are the runtime boundary and must be known before use. Models are
 * deliberately looser: a known provider can run a custom model ID even when the
 * catalog has not yet been refreshed for a new release.
 */
final readonly class ProviderInfo
{
    /**
     * @param  list<string>  $aliases
     * @param  array<string, ModelInfo>  $models
     * @param  array<string, mixed>  $cacheOptions
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $icon,
        public string $driver,
        public string $apiFormat,
        public string $authMode,
        public string $source,
        public ?string $defaultUrl,
        public ?string $apiKeyUrl,
        public ?string $defaultModel,
        public array $aliases,
        public array $models,
        public int $defaultContextWindow = 32_000,
        public int $defaultMaxOutputTokens = 4_096,
        public bool $supportsToolsByDefault = true,
        public bool $supportsStreamingByDefault = true,
        public bool $supportsPromptCacheByDefault = false,
        public array $cacheOptions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $id, array $data): self
    {
        $models = [];
        foreach (($data['models'] ?? []) as $modelId => $model) {
            if (is_array($model)) {
                $models[(string) $modelId] = ModelInfo::fromArray((string) $modelId, $model);
            }
        }

        return new self(
            id: $id,
            name: (string) ($data['name'] ?? str($id)->replace(['-', '_'], ' ')->title()),
            description: (string) ($data['description'] ?? "AI model provider {$id}."),
            icon: (string) ($data['icon'] ?? 'ph:brain'),
            driver: (string) ($data['driver'] ?? $id),
            apiFormat: (string) ($data['api_format'] ?? 'openai_compat'),
            authMode: (string) ($data['auth'] ?? 'api_key'),
            source: (string) ($data['source'] ?? 'catalog'),
            defaultUrl: isset($data['default_url']) ? (string) $data['default_url'] : null,
            apiKeyUrl: isset($data['api_key_url']) ? (string) $data['api_key_url'] : null,
            defaultModel: isset($data['default_model']) ? (string) $data['default_model'] : (array_key_first($models) ?: null),
            aliases: array_values(array_map('strval', is_array($data['aliases'] ?? null) ? $data['aliases'] : [])),
            models: $models,
            defaultContextWindow: (int) ($data['default_context_window'] ?? 32_000),
            defaultMaxOutputTokens: (int) ($data['default_max_output_tokens'] ?? 4_096),
            supportsToolsByDefault: (bool) ($data['supports_tools_by_default'] ?? true),
            supportsStreamingByDefault: (bool) ($data['supports_streaming_by_default'] ?? true),
            supportsPromptCacheByDefault: (bool) ($data['supports_prompt_cache_by_default'] ?? false),
            cacheOptions: is_array($data['cache_options'] ?? null) ? $data['cache_options'] : [],
        );
    }

    public function requiresApiKey(): bool
    {
        return $this->authMode === 'api_key' && $this->driver !== 'ollama';
    }

    public function model(string $model): ModelInfo
    {
        return $this->models[$model] ?? ModelInfo::unknown($model, $this);
    }
}
