<?php

namespace App\Domain\Web\ValueObjects;

final readonly class WebSearchResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $title,
        public string $url,
        public string $snippet = '',
        public ?float $score = null,
        public ?string $publishedAt = null,
        public ?string $source = null,
        public ?string $content = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'snippet' => $this->snippet,
            'score' => $this->score,
            'published_at' => $this->publishedAt,
            'source' => $this->source,
            'content' => $this->content,
            'metadata' => $this->metadata,
        ];
    }
}
