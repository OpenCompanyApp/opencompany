<?php

namespace App\Domain\Web\Providers;

use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;

class BraveProvider extends AbstractWebProvider implements WebSearchProvider
{
    public function id(): string
    {
        return 'brave';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $query = http_build_query(array_filter([
            'q' => $request->query,
            'count' => max(1, min(20, $request->maxResults)),
            'country' => $request->country,
            'search_lang' => $request->language,
            'freshness' => $request->recency,
        ], fn ($value) => $value !== null && $value !== ''));

        $data = $this->getJson($this->baseUrl(fallback: 'https://api.search.brave.com').'/res/v1/web/search?'.$query, [
            'X-Subscription-Token' => $this->apiKey(),
        ], $request->timeoutSeconds ?? 30);

        $results = [];
        foreach (is_array(($data['web'] ?? [])['results'] ?? null) ? $data['web']['results'] : [] as $item) {
            if (is_array($item)) {
                $url = $this->string($item, 'url');
                $results[] = new WebSearchResult($this->string($item, 'title', $url), $url, $request->includeSnippets ? strip_tags($this->string($item, 'description')) : '', publishedAt: $this->string($item, 'age') ?: null, source: $this->sourceUrl($url));
            }
        }

        return new WebSearchResponse($this->id(), $request->query, $results, metadata: ['query' => $data['query'] ?? []]);
    }
}
