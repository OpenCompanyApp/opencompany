<?php

namespace App\Domain\Web\ValueObjects;

final readonly class WebSearchResponse
{
    /**
     * @param  list<WebSearchResult>  $results
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $provider,
        public string $query,
        public array $results,
        public ?string $answer = null,
        public array $metadata = [],
        public bool $cacheHit = false,
    ) {}

    public function withCacheHit(bool $cacheHit): self
    {
        return new self($this->provider, $this->query, $this->results, $this->answer, $this->metadata, $cacheHit);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'query' => $this->query,
            'answer' => $this->answer,
            'results' => array_map(fn (WebSearchResult $result) => $result->toArray(), $this->results),
            'metadata' => $this->metadata,
            'cache_hit' => $this->cacheHit,
        ];
    }
}
