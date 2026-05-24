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

class ParallelProvider extends AbstractWebProvider implements WebSearchProvider, WebFetchProvider
{
    public function __construct(WebCredentialResolver $credentials, private WebRequestGuard $guard)
    {
        parent::__construct($credentials);
    }

    public function id(): string
    {
        return 'parallel';
    }

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.parallel.ai').'/v1beta/search', [
            'search_queries' => [$request->query],
            'objective' => $request->query,
            'processor' => $request->mode === 'deep' ? 'pro' : 'base',
            'max_results' => max(1, min(20, $request->maxResults)),
        ], ['x-api-key' => $this->apiKey()], $request->timeoutSeconds ?? 30);

        $raw = is_array($data['results'] ?? null) ? $data['results'] : (is_array($data['web'] ?? null) ? $data['web'] : []);
        $results = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $url = $this->string($item, 'url');
                $results[] = new WebSearchResult($this->string($item, 'title', $url), $url, $request->includeSnippets ? $this->string($item, 'description', $this->string($item, 'snippet')) : '', source: $this->sourceUrl($url));
            }
        }

        return new WebSearchResponse($this->id(), $request->query, $results, metadata: ['raw' => $data]);
    }

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $this->guard->assertSafePublicUrl($request->url);
        $data = $this->postJson($this->baseUrl(fallback: 'https://api.parallel.ai').'/v1beta/extract', [
            'urls' => [$request->url],
            'full_content' => true,
        ], ['x-api-key' => $this->apiKey()], $request->timeoutSeconds ?? 30);

        $item = is_array(($data['results'] ?? [])[0] ?? null) ? $data['results'][0] : $data;
        $content = $this->string($item, 'content', $this->string($item, 'text', $this->string($item, 'markdown')));

        return new WebFetchResponse($this->id(), $request->url, $this->string($item, 'url', $request->url), 200, null, $request->format, $this->string($item, 'title') ?: null, [], [], ['full' => $content], $this->limit($content, $request->outputLimitChars), extractionMethod: 'parallel_extract', meta: ['raw' => $data]);
    }
}
