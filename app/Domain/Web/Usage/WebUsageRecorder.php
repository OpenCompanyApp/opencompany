<?php

namespace App\Domain\Web\Usage;

use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;
use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;
use App\Models\WebUsageEvent;
use Illuminate\Support\Str;

/**
 * Records workspace-scoped web usage without persisting response bodies.
 *
 * The manager layer calls this after cache hits, successful provider calls, and
 * failed provider attempts. Any recorder failure is swallowed so diagnostics
 * cannot break the user-facing web tool path.
 */
class WebUsageRecorder
{
    public function recordSearchSuccess(WebSearchRequest $request, WebSearchResponse $response, bool $cacheHit): void
    {
        $this->record([
            'workspace_id' => $request->workspaceId,
            'agent_id' => $request->agentId,
            'user_id' => $request->userId,
            'capability' => 'search',
            'provider' => $response->provider,
            'request_hash' => $this->requestHash($request->cachePayload()),
            'query' => $request->query,
            'result_count' => count($response->results),
            'cache_hit' => $cacheHit,
            'success' => true,
            'metadata' => $this->safeMetadata($response->metadata),
        ]);
    }

    public function recordFetchSuccess(WebFetchRequest $request, WebFetchResponse $response, bool $cacheHit): void
    {
        $this->record([
            'workspace_id' => $request->workspaceId,
            'agent_id' => $request->agentId,
            'user_id' => $request->userId,
            'capability' => 'fetch',
            'provider' => $response->provider,
            'request_hash' => $this->requestHash($request->cachePayload()),
            'url' => $request->url,
            'final_url' => $response->finalUrl,
            'status_code' => $response->statusCode,
            'content_type' => $response->contentType,
            'bytes' => $this->bytes($response),
            'cache_hit' => $cacheHit,
            'success' => true,
            'metadata' => $this->safeMetadata($response->meta),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordFailure(string $capability, string $provider, array $payload, ?string $workspaceId, ?string $agentId, ?string $userId, \Throwable $error): void
    {
        $this->record([
            'workspace_id' => $workspaceId,
            'agent_id' => $agentId,
            'user_id' => $userId,
            'capability' => $capability,
            'provider' => $provider,
            'request_hash' => $this->requestHash($payload),
            'query' => is_string($payload['query'] ?? null) ? $payload['query'] : null,
            'url' => is_string($payload['url'] ?? null) ? $payload['url'] : null,
            'cache_hit' => false,
            'success' => false,
            'error' => Str::limit($error->getMessage(), 2000, ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): void
    {
        try {
            WebUsageEvent::query()->create(array_merge($attributes, [
                'id' => Str::uuid()->toString(),
                'occurred_at' => now(),
            ]));
        } catch (\Throwable) {
            // Web calls should remain available even when audit storage is down
            // or migrations have not run in a local development checkout.
        }
    }

    private function bytes(WebFetchResponse $response): ?int
    {
        $bytes = $response->meta['bytes'] ?? null;

        return is_int($bytes) ? $bytes : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata): array
    {
        return array_diff_key($metadata, array_flip(['headers', 'raw_response', 'content', 'html', 'body']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
