<?php

namespace App\Domain\Web\Providers;

use App\Domain\Web\Contracts\WebFetchProvider;
use App\Domain\Web\Contracts\WebSearchProvider;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Domain\Web\Support\WebCredentialResolver;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;

class FirecrawlProvider extends AbstractWebProvider implements WebSearchProvider, WebFetchProvider
{
    public function __construct(WebCredentialResolver $credentials, private WebRequestGuard $guard)
    {
        parent::__construct($credentials);
    }

    public function id(): string
    {
        return 'firecrawl';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.firecrawl.dev').'/v1/search', [
            'query' => $request->query,
            'limit' => max(1, min(20, $request->maxResults)),
        ], ['Authorization' => 'Bearer '.$this->apiKey()], $request->timeoutSeconds ?? 30);

        $raw = is_array($data['data'] ?? null) ? $data['data'] : (is_array($data['results'] ?? null) ? $data['results'] : []);
        $results = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $url = $this->string($item, 'url');
                $results[] = new WebSearchResult($this->string($item, 'title', $url), $url, $request->includeSnippets ? $this->string($item, 'description', $this->string($item, 'markdown')) : '', source: $this->sourceUrl($url));
            }
        }

        return new WebSearchResponse($this->id(), $request->query, $results, metadata: ['raw' => $data]);
    }

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $this->guard->assertSafePublicUrl($request->url);
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.firecrawl.dev').'/v1/scrape', [
            'url' => $request->url,
            'formats' => [$request->format === 'html' ? 'html' : 'markdown'],
        ], ['Authorization' => 'Bearer '.$this->apiKey()], $request->timeoutSeconds ?? 30);

        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $content = $request->format === 'html'
            ? $this->string($payload, 'html')
            : $this->string($payload, 'markdown', $this->string($payload, 'content'));
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        return new WebFetchResponse($this->id(), $request->url, $metadata['sourceURL'] ?? $request->url, 200, null, $request->format, $metadata['title'] ?? null, $metadata, [], ['full' => $content], $this->limit($content, $request->outputLimitChars), rawHtml: $request->format === 'html' ? $content : null, extractionMethod: 'firecrawl_scrape', meta: ['raw' => $data]);
    }
}
