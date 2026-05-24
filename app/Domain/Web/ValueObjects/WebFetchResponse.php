<?php

namespace App\Domain\Web\ValueObjects;

final readonly class WebFetchResponse
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<array{id: string, title: string, level: int}>  $outline
     * @param  array<string, string>  $sections
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $provider,
        public string $url,
        public ?string $finalUrl,
        public ?int $statusCode,
        public ?string $contentType,
        public string $format,
        public ?string $title,
        public array $metadata,
        public array $outline,
        public array $sections,
        public string $content,
        public ?string $rawHtml = null,
        public bool $truncated = false,
        public ?string $nextChunkToken = null,
        public ?string $extractionMethod = null,
        public array $meta = [],
        public bool $cacheHit = false,
    ) {}

    public function withContent(string $content, bool $truncated = false, ?string $nextChunkToken = null, array $meta = []): self
    {
        return new self(
            provider: $this->provider,
            url: $this->url,
            finalUrl: $this->finalUrl,
            statusCode: $this->statusCode,
            contentType: $this->contentType,
            format: $this->format,
            title: $this->title,
            metadata: $this->metadata,
            outline: $this->outline,
            sections: $this->sections,
            content: $content,
            rawHtml: $this->rawHtml,
            truncated: $truncated,
            nextChunkToken: $nextChunkToken,
            extractionMethod: $this->extractionMethod,
            meta: array_merge($this->meta, $meta),
            cacheHit: $this->cacheHit,
        );
    }

    public function withCacheHit(bool $cacheHit): self
    {
        return new self($this->provider, $this->url, $this->finalUrl, $this->statusCode, $this->contentType, $this->format, $this->title, $this->metadata, $this->outline, $this->sections, $this->content, $this->rawHtml, $this->truncated, $this->nextChunkToken, $this->extractionMethod, $this->meta, $cacheHit);
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'url' => $this->url,
            'final_url' => $this->finalUrl,
            'status_code' => $this->statusCode,
            'content_type' => $this->contentType,
            'format' => $this->format,
            'title' => $this->title,
            'metadata' => $this->metadata,
            'outline' => $this->outline,
            'sections' => $this->sections,
            'content' => $this->content,
            'truncated' => $this->truncated,
            'next_chunk_token' => $this->nextChunkToken,
            'extraction_method' => $this->extractionMethod,
            'meta' => $this->meta,
            'cache_hit' => $this->cacheHit,
        ];
    }
}
