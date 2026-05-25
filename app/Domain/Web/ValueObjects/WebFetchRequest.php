<?php

namespace App\Domain\Web\ValueObjects;

final readonly class WebFetchRequest
{
    public function __construct(
        public string $url,
        public ?string $provider = null,
        public string $mode = 'main',
        public string $format = 'markdown',
        public int $maxChars = 12000,
        public bool $summarize = false,
        public ?string $prompt = null,
        public ?string $heading = null,
        public ?string $sectionId = null,
        public ?string $match = null,
        public ?string $startAfter = null,
        public ?string $endBefore = null,
        public ?string $chunkToken = null,
        public ?int $timeoutSeconds = null,
        public string $strategy = 'auto',
        public bool $includeMetadata = true,
        public bool $includeOutline = true,
        public int $outputLimitChars = 100000,
        public bool $noCache = false,
        public ?string $workspaceId = null,
        public ?string $agentId = null,
        public ?string $userId = null,
    ) {}

    public function normalized(): self
    {
        return new self(
            url: trim($this->url),
            provider: $this->provider !== null ? str_replace('-', '_', strtolower(trim($this->provider))) : null,
            mode: in_array($this->mode, ['metadata', 'outline', 'main', 'full', 'section', 'match', 'chunk'], true) ? $this->mode : 'main',
            format: in_array($this->format, ['markdown', 'text', 'html'], true) ? $this->format : 'markdown',
            maxChars: max(50, min(50000, $this->maxChars)),
            summarize: $this->summarize,
            prompt: $this->nullable($this->prompt),
            heading: $this->nullable($this->heading),
            sectionId: $this->nullable($this->sectionId),
            match: $this->nullable($this->match),
            startAfter: $this->nullable($this->startAfter),
            endBefore: $this->nullable($this->endBefore),
            chunkToken: $this->nullable($this->chunkToken),
            timeoutSeconds: $this->timeoutSeconds !== null ? max(1, min(120, $this->timeoutSeconds)) : null,
            strategy: in_array($this->strategy, ['auto', 'direct_only', 'provider_only'], true) ? $this->strategy : 'auto',
            includeMetadata: $this->includeMetadata,
            includeOutline: $this->includeOutline,
            outputLimitChars: max(1000, min(200000, $this->outputLimitChars)),
            noCache: $this->noCache,
            workspaceId: $this->workspaceId,
            agentId: $this->agentId,
            userId: $this->userId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cachePayload(): array
    {
        return [
            'url' => $this->url,
            'provider' => $this->provider,
            'format' => $this->format,
            'timeoutSeconds' => $this->timeoutSeconds,
            'strategy' => $this->strategy,
            'outputLimitChars' => $this->outputLimitChars,
        ];
    }

    private function nullable(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
