<?php

namespace App\Agents\Tools\Workspace;

use App\Models\McpServer;
use App\Models\User;
use App\Services\Mcp\McpClient;
use App\Services\Mcp\McpToolProvider;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class DiscoverMcpTools implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Refresh tool discovery for an MCP server to update the list of available tools.';
    }

    public function handle(Request $request): string
    {
        try {
            $server = $this->findServer($request);
            if (is_string($server)) {
                return $server;
            }

            try {
                $client = McpClient::fromServer($server);
                $tools = $client->listTools();

                $server->update([
                    'discovered_tools' => $tools,
                    'tools_discovered_at' => now(),
                    'enabled' => true,
                ]);

                // Re-register provider
                $registry = app(ToolProviderRegistry::class);
                $registry->register(new McpToolProvider($server));

                $toolCount = count($tools);
                $toolNames = collect($tools)->pluck('name')->implode(', ');

                return "Discovered {$toolCount} tools for {$server->name}: {$toolNames}. Server enabled.";
            } catch (\Throwable $e) {
                return "Tool discovery failed for {$server->name}: {$e->getMessage()}";
            }
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function findServer(Request $request): McpServer|string
    {
        $identifier = $request['serverId'] ?? null;
        if (!$identifier) {
            return "serverId is required. Use list_mcp_servers to see available servers.";
        }

        $server = McpServer::forWorkspace()->find($identifier);
        if ($server) {
            return $server;
        }

        $server = McpServer::forWorkspace()->where('slug', $identifier)->first();
        if ($server) {
            return $server;
        }

        $server = McpServer::forWorkspace()->whereRaw('LOWER(name) = ?', [strtolower($identifier)])->first();
        if ($server) {
            return $server;
        }

        return "MCP server not found: '{$identifier}'. Use list_mcp_servers to see available servers.";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'serverId' => $schema
                ->string()
                ->description('Server ID, slug, or name.')
                ->required(),
        ];
    }
}
