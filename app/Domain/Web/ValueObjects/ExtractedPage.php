<?php

namespace App\Domain\Web\ValueObjects;

final readonly class ExtractedPage
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<array{id: string, title: string, level: int}>  $outline
     * @param  array<string, string>  $sections
     */
    public function __construct(
        public ?string $title,
        public array $metadata,
        public array $outline,
        public array $sections,
        public string $fullContent,
    ) {}
}
