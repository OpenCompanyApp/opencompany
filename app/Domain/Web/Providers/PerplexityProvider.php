<?php

namespace App\Domain\Web\Providers;

use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;

class PerplexityProvider extends AbstractWebProvider implements WebSearchProvider
{
    public function id(): string
    {
        return 'perplexity';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $body = array_filter([
            'query' => $request->query,
            'max_results' => max(1, min(20, $request->maxResults)),
            'country' => $request->country,
            'search_language_filter' => $request->language !== null ? [$request->language] : null,
            'search_recency_filter' => $request->recency,
            'max_tokens' => 10000,
        ], fn ($value) => $value !== null);

        if ($request->allowedDomains !== []) {
            $body['search_domain_filter'] = $request->allowedDomains;
        } elseif ($request->blockedDomains !== []) {
            $body['search_domain_filter'] = array_map(fn (string $domain) => '-'.$domain, $request->blockedDomains);
        }

        $data = $this->postJson($this->baseUrl(fallback: 'https://api.perplexity.ai').'/search', $body, [
            'Authorization' => 'Bearer '.$this->apiKey(),
        ], $request->timeoutSeconds ?? 30);

        $results = [];
        foreach (is_array($data['results'] ?? null) ? $data['results'] : [] as $item) {
            if (is_array($item)) {
                $url = $this->string($item, 'url');
                $results[] = new WebSearchResult($this->string($item, 'title', $url), $url, $request->includeSnippets ? $this->string($item, 'snippet') : '', publishedAt: $this->string($item, 'date') ?: null, source: $this->sourceUrl($url), metadata: ['last_updated' => $item['last_updated'] ?? null]);
            }
        }

        return new WebSearchResponse($this->id(), $request->query, $results, metadata: ['id' => $data['id'] ?? null]);
    }
}
