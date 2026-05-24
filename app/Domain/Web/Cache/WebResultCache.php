<?php

namespace App\Domain\Web\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Workspace-scoped transient cache for normalized web results.
 *
 * The cache key includes capability, provider, normalized request payload, and
 * workspace id so one tenant cannot observe another tenant's fetched/search
 * data. Raw provider secrets are never included in cache keys or values.
 */
class WebResultCache
{
    public function get(string $capability, string $provider, array $payload, ?string $workspaceId): mixed
    {
        if (! config('web.cache.enabled', true)) {
            return null;
        }

        return Cache::get($this->key($capability, $provider, $payload, $workspaceId));
    }

    public function put(string $capability, string $provider, array $payload, ?string $workspaceId, mixed $value): void
    {
        if (! config('web.cache.enabled', true)) {
            return;
        }

        Cache::put(
            $this->key($capability, $provider, $payload, $workspaceId),
            $value,
            now()->addSeconds((int) config('web.cache.ttl_seconds', 900)),
        );
    }

    private function key(string $capability, string $provider, array $payload, ?string $workspaceId): string
    {
        return 'web:'.hash('sha256', json_encode([
            'workspace_id' => $workspaceId ?? (app()->bound('currentWorkspace') ? workspace()->id : 'global'),
            'capability' => $capability,
            'provider' => $provider,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }
}
