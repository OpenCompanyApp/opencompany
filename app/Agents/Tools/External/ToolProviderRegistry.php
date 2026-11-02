<?php

namespace App\Agents\Tools\External;

use App\Agents\Tools\External\ToolProvider;

/**
 * External (MCP) tool provider registry. App-owned since the integration package system was deleted (2026-11-02); MCP servers are the only external providers until Connections land.
 */
class ToolProviderRegistry
{
    /** @var array<string, ToolProvider> */
    private array $providers = [];

    public function register(ToolProvider $provider): void
    {
        $this->providers[$provider->appName()] = $provider;
    }

    /** @return array<string, ToolProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $appName): ?ToolProvider
    {
        return $this->providers[$appName] ?? null;
    }

    public function has(string $appName): bool
    {
        return isset($this->providers[$appName]);
    }
}
