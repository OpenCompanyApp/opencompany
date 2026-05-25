<?php

namespace App\Domain\Ai\Catalog;

/**
 * Catalog metadata for one model ID.
 *
 * Unknown model IDs are valid runtime inputs. In that case callers should use
 * provider-level fallbacks rather than blocking execution because the committed
 * catalog naturally lags brand-new model releases.
 */
final readonly class ModelInfo
{
    public function __construct(
        public string $id,
        public string $label,
        public int $contextWindow = 32_000,
        public int $maxOutputTokens = 4_096,
        public ?PricingInfo $pricing = null,
        public bool $supportsTools = true,
        public bool $supportsStreaming = true,
        public bool $supportsPromptCache = false,
        public bool $supportsReasoning = false,
        public string $status = 'known',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $id, array $data): self
    {
        return new self(
            id: $id,
            label: (string) ($data['label'] ?? $data['display_name'] ?? self::formatLabel($id)),
            contextWindow: (int) ($data['context_window'] ?? $data['context'] ?? 32_000),
            maxOutputTokens: (int) ($data['max_output_tokens'] ?? $data['max_output'] ?? 4_096),
            pricing: isset($data['pricing']) && is_array($data['pricing']) ? PricingInfo::fromArray($data['pricing']) : null,
            supportsTools: (bool) ($data['supports_tools'] ?? true),
            supportsStreaming: (bool) ($data['supports_streaming'] ?? true),
            supportsPromptCache: (bool) ($data['supports_prompt_cache'] ?? false),
            supportsReasoning: (bool) ($data['supports_reasoning'] ?? $data['thinking'] ?? false),
            status: (string) ($data['status'] ?? 'known'),
        );
    }

    public static function unknown(string $id, ProviderInfo $provider): self
    {
        return new self(
            id: $id,
            label: self::formatLabel($id),
            contextWindow: $provider->defaultContextWindow,
            maxOutputTokens: $provider->defaultMaxOutputTokens,
            pricing: null,
            supportsTools: $provider->supportsToolsByDefault,
            supportsStreaming: $provider->supportsStreamingByDefault,
            supportsPromptCache: $provider->supportsPromptCacheByDefault,
            status: 'unknown',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'context_window' => $this->contextWindow,
            'max_output_tokens' => $this->maxOutputTokens,
            'pricing' => $this->pricing?->toArray(),
            'supports_tools' => $this->supportsTools,
            'supports_streaming' => $this->supportsStreaming,
            'supports_prompt_cache' => $this->supportsPromptCache,
            'supports_reasoning' => $this->supportsReasoning,
            'status' => $this->status,
        ];
    }

    private static function formatLabel(string $modelId): string
    {
        $name = preg_replace('/-\d{8}$/', '', $modelId) ?: $modelId;
        $name = str_replace(['-', '_', '/'], [' ', ' ', ' / '], $name);

        return ucwords($name);
    }
}
