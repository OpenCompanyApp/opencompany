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

class ExaProvider extends AbstractWebProvider implements WebSearchProvider, WebFetchProvider
{
    public function __construct(WebCredentialResolver $credentials, private WebRequestGuard $guard)
    {
        parent::__construct($credentials);
    }

    public function id(): string
    {
        return 'exa';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.exa.ai').'/search', [
            'query' => $request->query,
            'numResults' => max(1, min(20, $request->maxResults)),
            'type' => in_array($request->mode, ['auto', 'fast', 'deep'], true) ? $request->mode : 'auto',
            'contents' => ['text' => ['maxCharacters' => 1000]],
        ], ['x-api-key' => $this->apiKey()], $request->timeoutSeconds ?? 30);

        $results = [];
        foreach (is_array($data['results'] ?? null) ? $data['results'] : [] as $item) {
            if (is_array($item)) {
                $url = $this->string($item, 'url');
                $results[] = new WebSearchResult($this->string($item, 'title', $url), $url, $request->includeSnippets ? $this->string($item, 'text') : '', isset($item['score']) && is_numeric($item['score']) ? (float) $item['score'] : null, $this->string($item, 'publishedDate') ?: null, $this->sourceUrl($url));
            }
        }

        return new WebSearchResponse($this->id(), $request->query, $results, metadata: ['requestId' => $data['requestId'] ?? null]);
    }

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $this->guard->assertSafePublicUrl($request->url);
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.exa.ai').'/contents', [
            'urls' => [$request->url],
            'text' => ['maxCharacters' => $request->outputLimitChars],
            'livecrawl' => 'fallback',
        ], ['x-api-key' => $this->apiKey()], $request->timeoutSeconds ?? 30);

        $item = is_array(($data['results'] ?? [])[0] ?? null) ? $data['results'][0] : [];
        $content = $this->string($item, 'text');

        return new WebFetchResponse($this->id(), $request->url, $this->string($item, 'url', $request->url), 200, 'text/plain', 'text', $this->string($item, 'title') ?: null, [], [], ['full' => $content], $this->limit($content, $request->outputLimitChars), extractionMethod: 'exa_contents');
    }
}
