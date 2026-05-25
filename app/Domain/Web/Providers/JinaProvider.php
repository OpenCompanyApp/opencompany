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

class JinaProvider extends AbstractWebProvider implements WebFetchProvider, WebSearchProvider
{
    public function __construct(WebCredentialResolver $credentials, private WebRequestGuard $guard)
    {
        parent::__construct($credentials);
    }

    public function id(): string
    {
        return 'jina';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $text = $this->getText(($this->baseUrl('search_url', 'https://s.jina.ai')).'/'.rawurlencode($request->query), $this->headers(), $request->timeoutSeconds ?? 30);
        $results = [];
        foreach ($this->linksFromMarkdown($text, $request->maxResults) as $link) {
            $results[] = new WebSearchResult($link['title'], $link['url'], $request->includeSnippets ? $link['snippet'] : '', source: $this->sourceUrl($link['url']));
        }

        if ($results === []) {
            $results[] = new WebSearchResult('Jina search response', '', $this->limit($text, $request->outputLimitChars));
        }

        return new WebSearchResponse($this->id(), $request->query, $results, metadata: ['raw_response' => $this->limit($text, $request->outputLimitChars)]);
    }

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $this->guard->assertSafePublicUrl($request->url);
        $content = $this->getText(($this->baseUrl('reader_url', 'https://r.jina.ai')).'/'.preg_replace('#^https?://#', 'http://', $request->url), $this->headers(), $request->timeoutSeconds ?? 30);

        return new WebFetchResponse($this->id(), $request->url, $request->url, 200, 'text/markdown', 'markdown', null, [], [], ['full' => $content], $this->limit($content, $request->outputLimitChars), extractionMethod: 'jina_reader');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $apiKey = $this->credentials->apiKey($this->id());

        return $apiKey === '' ? [] : ['Authorization' => 'Bearer '.$apiKey];
    }

    /**
     * @return list<array{title: string, url: string, snippet: string}>
     */
    private function linksFromMarkdown(string $text, int $limit): array
    {
        preg_match_all('/\[(?<title>[^\]]+)\]\((?<url>https?:\/\/[^)]+)\)(?<tail>[^\n]*)/', $text, $matches, PREG_SET_ORDER);
        $links = [];
        foreach (array_slice($matches, 0, max(1, $limit)) as $match) {
            $links[] = ['title' => trim($match['title']), 'url' => trim($match['url']), 'snippet' => trim($match['tail'])];
        }

        return $links;
    }
}
