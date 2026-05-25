<?php

namespace App\Domain\Ai\Catalog;

/**
 * Immutable token-pricing snapshot for one model.
 *
 * Prices are expressed in USD per one million tokens. The catalog uses these
 * values only for estimates; provider-reported billing remains authoritative
 * when a provider such as OpenRouter returns an actual charged amount.
 */
final readonly class PricingInfo
{
    public function __construct(
        public ?float $inputPerMillion = null,
        public ?float $outputPerMillion = null,
        public ?float $cacheReadPerMillion = null,
        public ?float $cacheWritePerMillion = null,
        public string $kind = 'paid',
        public string $source = 'catalog',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inputPerMillion: isset($data['input_usd_per_million']) ? (float) $data['input_usd_per_million'] : null,
            outputPerMillion: isset($data['output_usd_per_million']) ? (float) $data['output_usd_per_million'] : null,
            cacheReadPerMillion: isset($data['cache_read_usd_per_million']) ? (float) $data['cache_read_usd_per_million'] : null,
            cacheWritePerMillion: isset($data['cache_write_usd_per_million']) ? (float) $data['cache_write_usd_per_million'] : null,
            kind: (string) ($data['kind'] ?? 'paid'),
            source: (string) ($data['source'] ?? 'catalog'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'input_usd_per_million' => $this->inputPerMillion,
            'output_usd_per_million' => $this->outputPerMillion,
            'cache_read_usd_per_million' => $this->cacheReadPerMillion,
            'cache_write_usd_per_million' => $this->cacheWritePerMillion,
            'kind' => $this->kind,
            'source' => $this->source,
        ];
    }
}
