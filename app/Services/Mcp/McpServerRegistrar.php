<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class McpServerRegistrar
{
    /**
     * Register all enabled MCP servers as ToolProviders in the registry.
     */
    public static function registerAll(ToolProviderRegistry $registry): void
    {
        try {
            $servers = McpServer::where('enabled', true)
                ->whereNotNull('discovered_tools')
                ->get();
        } catch (\Throwable) {
            // Table may not exist yet (fresh install / migration pending)
            return;
        }

        foreach ($servers as $server) {
            $registry->register(new McpToolProvider($server));
        }
    }
}
