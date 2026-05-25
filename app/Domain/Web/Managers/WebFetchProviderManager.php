<?php

namespace App\Domain\Web\Managers;

use App\Domain\Web\Cache\WebResultCache;
use App\Domain\Web\Exceptions\WebFetchPermanentException;
use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Extraction\MarkdownPageExtractor;
use App\Domain\Web\Registry\WebProviderRegistry;
use App\Domain\Web\Safety\WebAccessPolicy;
use App\Domain\Web\Usage\WebUsageRecorder;
use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;
use App\Models\AppSetting;

/**
 * Selects fetch providers and applies cache/fallback policy.
 */
class WebFetchProviderManager
{
    public function __construct(
        private WebProviderRegistry $registry,
        private WebResultCache $cache,
        private MarkdownPageExtractor $markdown,
        private WebAccessPolicy $policy,
        private WebUsageRecorder $usage,
    ) {}

    public function fetch(WebFetchRequest $request): WebFetchResponse
    {
        $request = $request->normalized();
        if ($request->url === '') {
            throw new WebProviderException('URL is required.');
        }

        try {
            $this->policy->assertUrlAllowed($request->url);
        } catch (WebFetchPermanentException $e) {
            $this->usage->recordFailure('fetch', $request->provider ?? 'policy', $request->cachePayload(), $request->workspaceId, $request->agentId, $request->userId, $e);
            throw $e;
        }

        $errors = [];
        foreach ($this->providerOrder($request) as $providerId) {
            try {
                $provider = $this->registry->fetchProvider($providerId);
                if (! $provider->isAvailable()) {
                    throw new WebProviderException($provider->label().' is not configured.');
                }

                if ($provider->id() !== 'direct' && ! (bool) $this->settingValue('web_fetch_allow_external', config('web.fetch.allow_external', false))) {
                    throw new WebProviderException('External web fetch providers are disabled for this workspace.');
                }

                if (! $request->noCache && ($cached = $this->cache->get('fetch', $provider->id(), $request->cachePayload(), $request->workspaceId)) instanceof WebFetchResponse) {
                    $cached = $cached->withCacheHit(true);
                    $this->usage->recordFetchSuccess($request, $cached, true);

                    return $cached;
                }

                $response = $this->normalizeProviderResponse($provider->fetch($request));
                if ($response->finalUrl !== null) {
                    $this->policy->assertUrlAllowed($response->finalUrl);
                }
                if (! $request->noCache) {
                    $this->cache->put('fetch', $provider->id(), $request->cachePayload(), $request->workspaceId, $response);
                }

                $this->usage->recordFetchSuccess($request, $response, false);

                return $response;
            } catch (WebFetchPermanentException $e) {
                $this->usage->recordFailure('fetch', $providerId, $request->cachePayload(), $request->workspaceId, $request->agentId, $request->userId, $e);
                throw $e;
            } catch (\Throwable $e) {
                $this->usage->recordFailure('fetch', $providerId, $request->cachePayload(), $request->workspaceId, $request->agentId, $request->userId, $e);
                $errors[] = "{$providerId}: {$e->getMessage()}";
            }
        }

        throw new WebProviderException('All web fetch providers failed: '.implode('; ', $errors));
    }

    /**
     * @return list<string>
     */
    public function availableProviderIds(): array
    {
        return $this->registry->availableFetchProviderIds();
    }

    /**
     * @return list<string>
     */
    private function providerOrder(WebFetchRequest $request): array
    {
        if ($request->provider !== null) {
            return [$request->provider];
        }

        if ($request->strategy === 'direct_only') {
            return ['direct'];
        }

        $default = (string) $this->settingValue('web_fetch_default_provider', config('web.fetch.default_provider', 'direct'));
        $configuredFallbacks = $this->settingValue('web_fetch_fallback_providers', config('web.fetch.fallback_providers', []));
        $fallbacks = is_array($configuredFallbacks) ? $configuredFallbacks : [];
        $providers = $request->strategy === 'provider_only'
            ? array_values(array_filter(array_merge([$default === 'direct' ? null : $default], $fallbacks), fn ($id) => $id !== null && $id !== 'direct'))
            : array_merge([$default], $fallbacks);

        return array_values(array_unique(array_filter($providers, is_string(...))));
    }

    private function normalizeProviderResponse(WebFetchResponse $response): WebFetchResponse
    {
        if ($response->outline !== [] || trim($response->content) === '') {
            return $response;
        }

        $page = $this->markdown->extract($response->content, $response->title, $response->metadata);

        return new WebFetchResponse(
            provider: $response->provider,
            url: $response->url,
            finalUrl: $response->finalUrl,
            statusCode: $response->statusCode,
            contentType: $response->contentType,
            format: $response->format,
            title: $page->title,
            metadata: $page->metadata,
            outline: $page->outline,
            sections: $page->sections,
            content: $page->fullContent,
            rawHtml: $response->rawHtml,
            truncated: $response->truncated,
            nextChunkToken: $response->nextChunkToken,
            extractionMethod: $response->extractionMethod,
            meta: $response->meta,
            cacheHit: $response->cacheHit,
        );
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        return app()->bound('currentWorkspace') ? AppSetting::getValue($key, $default) : $default;
    }
}
