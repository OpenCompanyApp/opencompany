<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

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

        // Group by slug — default account first
        $grouped = [];
        foreach ($servers as $server) {
            $grouped[$server->slug][] = $server;
        }

        foreach ($grouped as $slug => $group) {
            // Find the default server (account_alias = '')
            $default = null;
            $accounts = [];

            foreach ($group as $server) {
                if ($server->account_alias === '' || $server->account_alias === null) {
                    $default = $server;
                } else {
                    $accounts[$server->account_alias] = $server;
                }
            }

            // Fall back to first server if no explicit default
            if ($default === null) {
                $default = $group[0];
            }

            $provider = new McpToolProvider($default);

            foreach ($accounts as $alias => $accountServer) {
                $provider->addAccountServer($alias, $accountServer);
            }

            $registry->register($provider);
        }
    }
}
