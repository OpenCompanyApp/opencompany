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

class TavilyProvider extends AbstractWebProvider implements WebFetchProvider, WebSearchProvider
{
    public function __construct(WebCredentialResolver $credentials, private WebRequestGuard $guard)
    {
        parent::__construct($credentials);
    }

    public function id(): string
    {
        return 'tavily';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.tavily.com').'/search', array_filter([
            'query' => $request->query,
            'max_results' => max(1, min(20, $request->maxResults)),
            'search_depth' => in_array($request->mode, ['deep', 'advanced'], true) || $request->searchDepth === 'advanced' ? 'advanced' : 'basic',
            'include_answer' => $request->includeAnswer,
            'include_raw_content' => false,
            'include_domains' => $request->allowedDomains,
            'exclude_domains' => $request->blockedDomains,
            'topic' => $request->recency === 'news' ? 'news' : 'general',
            'country' => $request->country,
        ], fn ($value) => $value !== null && $value !== []), [
            'Authorization' => 'Bearer '.$this->apiKey(),
        ], $request->timeoutSeconds ?? 30);

        $results = [];
        foreach (is_array($data['results'] ?? null) ? $data['results'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $results[] = new WebSearchResult(
                title: $this->string($item, 'title', $this->string($item, 'url')),
                url: $this->string($item, 'url'),
                snippet: $request->includeSnippets ? $this->string($item, 'content') : '',
                score: isset($item['score']) && is_numeric($item['score']) ? (float) $item['score'] : null,
                publishedAt: $this->string($item, 'published_date') ?: null,
                source: $this->sourceUrl($this->string($item, 'url')),
                metadata: ['raw_content_available' => isset($item['raw_content'])],
            );
        }

        return new WebSearchResponse($this->id(), $request->query, $results, is_string($data['answer'] ?? null) ? $this->limit($data['answer'], $request->outputLimitChars) : null, ['raw' => $data]);
    }

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $this->guard->assertSafePublicUrl($request->url);
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.tavily.com').'/extract', [
            'urls' => [$request->url],
            'extract_depth' => 'basic',
            'format' => $request->format === 'html' ? 'html' : 'markdown',
        ], ['Authorization' => 'Bearer '.$this->apiKey()], $request->timeoutSeconds ?? 30);

        $item = is_array(($data['results'] ?? [])[0] ?? null) ? $data['results'][0] : [];
        $content = $this->string($item, 'raw_content', $this->string($item, 'content'));

        return new WebFetchResponse($this->id(), $request->url, $this->string($item, 'url', $request->url), 200, null, $request->format, $this->string($item, 'title') ?: null, [], [], ['full' => $content], $this->limit($content, $request->outputLimitChars), meta: ['failed_results' => $data['failed_results'] ?? []]);
    }
}
