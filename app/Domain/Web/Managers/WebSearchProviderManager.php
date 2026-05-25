<?php

namespace App\Domain\Web\Managers;

use App\Domain\Web\Cache\WebResultCache;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Registry\WebProviderRegistry;
use App\Domain\Web\Safety\WebAccessPolicy;
use App\Domain\Web\Usage\WebUsageRecorder;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Domain\Web\ValueObjects\WebSearchResult;
use App\Models\AppSetting;

/**
 * Selects search providers, applies cache/fallback, and enforces domain policy.
 */
class WebSearchProviderManager
{
    public function __construct(
        private WebProviderRegistry $registry,
        private WebResultCache $cache,
        private WebAccessPolicy $policy,
        private WebUsageRecorder $usage,
    ) {}

    public function search(WebSearchRequest $request): WebSearchResponse
    {
        $request = $request->normalized();
        if ($request->query === '') {
            throw new WebProviderException('Search query is required.');
        }

        $errors = [];
        foreach ($this->providerOrder($request) as $providerId) {
            try {
                $provider = $this->registry->searchProvider($providerId);
                if (! $provider->isAvailable()) {
                    throw new WebProviderException($provider->label().' is not configured.');
                }

                if (! $request->noCache && ($cached = $this->cache->get('search', $provider->id(), $request->cachePayload(), $request->workspaceId)) instanceof WebSearchResponse) {
                    $cached = $cached->withCacheHit(true);
                    $this->usage->recordSearchSuccess($request, $cached, true);

                    return $cached;
                }

                $response = $provider->search($request);
                $response = new WebSearchResponse($response->provider, $response->query, $this->filterResults($response->results, $request), $response->answer, $response->metadata);

                if (! $request->noCache) {
                    $this->cache->put('search', $provider->id(), $request->cachePayload(), $request->workspaceId, $response);
                }

                $this->usage->recordSearchSuccess($request, $response, false);

                return $response;
            } catch (\Throwable $e) {
                $this->usage->recordFailure('search', $providerId, $request->cachePayload(), $request->workspaceId, $request->agentId, $request->userId, $e);
                $errors[] = "{$providerId}: {$e->getMessage()}";
            }
        }

        throw new WebProviderException('All web search providers failed: '.implode('; ', $errors));
    }

    /**
     * @return list<string>
     */
    public function availableProviderIds(): array
    {
        return $this->registry->availableSearchProviderIds();
    }

    /**
     * @return list<string>
     */
    private function providerOrder(WebSearchRequest $request): array
    {
        $providers = [];
        if ($request->provider !== null) {
            $providers[] = $request->provider;
        } else {
            $providers[] = (string) $this->settingValue('web_search_default_provider', config('web.search.default_provider', 'tavily'));
            $fallbacks = $this->settingValue('web_search_fallback_providers', config('web.search.fallback_providers', []));
            $providers = array_merge($providers, is_array($fallbacks) ? $fallbacks : []);
        }

        return array_values(array_unique(array_filter($providers, is_string(...))));
    }

    /**
     * @param  list<WebSearchResult>  $results
     * @return list<WebSearchResult>
     */
    private function filterResults(array $results, WebSearchRequest $request): array
    {
        $filtered = [];
        foreach ($results as $result) {
            $host = strtolower((string) parse_url($result->url, PHP_URL_HOST));
            if (! $this->policy->allowsHost($host, $request->allowedDomains, $request->blockedDomains)) {
                continue;
            }
            $filtered[] = $result;
        }

        return array_slice($filtered, 0, $request->maxResults);
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        return app()->bound('currentWorkspace') ? AppSetting::getValue($key, $default) : $default;
    }
}
