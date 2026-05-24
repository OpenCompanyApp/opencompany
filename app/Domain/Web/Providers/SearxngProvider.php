<?php

namespace App\Domain\Web\Providers;

use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;

class SearxngProvider extends AbstractWebProvider implements WebSearchProvider
{
    public function id(): string
    {
        return 'searxng';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $base = $this->baseUrl();
        if ($base === '') {
            throw new WebProviderException('SearXNG base URL is not configured.');
        }

        $query = http_build_query([
            'q' => $request->query,
            'format' => 'json',
            'language' => $request->language ?: 'auto',
        ]);
        $data = $this->getJson($base.'/search?'.$query, [], $request->timeoutSeconds ?? 30);

        $results = [];
        foreach (array_slice(is_array($data['results'] ?? null) ? $data['results'] : [], 0, $request->maxResults) as $item) {
            if (is_array($item)) {
                $url = $this->string($item, 'url');
                $results[] = new WebSearchResult($this->string($item, 'title', $url), $url, $request->includeSnippets ? $this->string($item, 'content') : '', isset($item['score']) && is_numeric($item['score']) ? (float) $item['score'] : null, source: $this->sourceUrl($url), metadata: ['engine' => $item['engine'] ?? null]);
            }
        }

        return new WebSearchResponse($this->id(), $request->query, $results, metadata: ['answers' => $data['answers'] ?? []]);
    }
}
