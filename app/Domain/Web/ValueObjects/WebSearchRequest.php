<?php

namespace App\Domain\Web\ValueObjects;

final readonly class WebSearchRequest
{
    /**
     * @param  list<string>  $allowedDomains
     * @param  list<string>  $blockedDomains
     */
    public function __construct(
        public string $query,
        public ?string $provider = null,
        public int $maxResults = 8,
        public array $allowedDomains = [],
        public array $blockedDomains = [],
        public string $searchDepth = 'basic',
        public bool $includeSnippets = true,
        public bool $includeAnswer = false,
        public ?string $mode = null,
        public ?string $country = null,
        public ?string $language = null,
        public ?string $recency = null,
        public ?int $timeoutSeconds = null,
        public int $outputLimitChars = 60000,
        public bool $noCache = false,
        public ?string $workspaceId = null,
        public ?string $agentId = null,
        public ?string $userId = null,
    ) {}

    public function normalized(): self
    {
        return new self(
            query: trim($this->query),
            provider: $this->provider !== null ? $this->normalizeProvider($this->provider) : null,
            maxResults: max(1, min(20, $this->maxResults)),
            allowedDomains: $this->normalizeDomains($this->allowedDomains),
            blockedDomains: $this->normalizeDomains($this->blockedDomains),
            searchDepth: in_array($this->searchDepth, ['basic', 'advanced'], true) ? $this->searchDepth : 'basic',
            includeSnippets: $this->includeSnippets,
            includeAnswer: $this->includeAnswer,
            mode: $this->mode !== null && in_array($this->mode, ['auto', 'fast', 'deep', 'basic', 'advanced'], true) ? $this->mode : null,
            country: $this->nullable($this->country),
            language: $this->nullable($this->language),
            recency: $this->nullable($this->recency),
            timeoutSeconds: $this->timeoutSeconds !== null ? max(1, min(120, $this->timeoutSeconds)) : null,
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
            'query' => $this->query,
            'provider' => $this->provider,
            'maxResults' => $this->maxResults,
            'allowedDomains' => $this->allowedDomains,
            'blockedDomains' => $this->blockedDomains,
            'searchDepth' => $this->searchDepth,
            'includeSnippets' => $this->includeSnippets,
            'includeAnswer' => $this->includeAnswer,
            'mode' => $this->mode,
            'country' => $this->country,
            'language' => $this->language,
            'recency' => $this->recency,
            'timeoutSeconds' => $this->timeoutSeconds,
            'outputLimitChars' => $this->outputLimitChars,
        ];
    }

    private function normalizeProvider(string $provider): string
    {
        return str_replace('-', '_', strtolower(trim($provider)));
    }

    private function nullable(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * @param  list<string>  $domains
     * @return list<string>
     */
    private function normalizeDomains(array $domains): array
    {
        $normalized = [];
        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));
            $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
            $domain = trim(explode('/', $domain)[0]);
            if ($domain !== '') {
                $normalized[] = $domain;
            }
        }

        return array_values(array_unique($normalized));
    }
}
