<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use App\Agents\Tools\External\ToolProviderRegistry;

class McpServerRegistrar
{
    /**
     * Register all enabled MCP servers as ToolProviders in the registry.
     *
     * Servers sharing the same slug are grouped: the default (account_alias='')
     * provides the canonical tool definitions, and additional accounts are
     * registered on the same provider for multi-account namespace support.
     */
    public static function registerAll(ToolProviderRegistry $registry): void
    {
        try {
            $servers = McpServer::where('enabled', true)
                ->whereNotNull('discovered_tools')
                ->get();
        } catch (\Throwable) {
            return;
        }

        // One provider per slug holds every workspace's servers; the provider
        // itself only ever exposes the bound workspace's group (see
        // McpToolProvider). Tool slugs stay `mcp_{slug}__{tool}` so existing
        // permission rows keep matching.
        $providers = [];
        foreach ($servers as $server) {
            if (! isset($providers[$server->slug])) {
                $providers[$server->slug] = new McpToolProvider($server);

                continue;
            }
            $providers[$server->slug]->addServer($server);
        }

        foreach ($providers as $provider) {
            $registry->register($provider);
        }
    }

    /**
     * Register or refresh ONE server without disturbing other workspaces'
     * servers that share its slug (a plain register() would replace them).
     */
    public static function register(ToolProviderRegistry $registry, McpServer $server): void
    {
        $existing = $registry->get('mcp_'.$server->slug);
        if ($existing instanceof McpToolProvider) {
            $existing->addServer($server);

            return;
        }

        $registry->register(new McpToolProvider($server));
    }

    /**
     * Stop exposing a removed or disabled server in this process.
     */
    public static function forget(ToolProviderRegistry $registry, McpServer $server): void
    {
        $existing = $registry->get('mcp_'.$server->slug);
        if ($existing instanceof McpToolProvider) {
            $existing->removeServer($server);
        }
    }
}
