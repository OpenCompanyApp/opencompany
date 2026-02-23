<?php

namespace App\Agents\Tools\Workspace;

use App\Models\McpServer;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListMcpServers implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all configured MCP servers in the workspace with their status and discovered tools.';
    }

    public function handle(Request $request): string
    {
        try {
            $servers = McpServer::forWorkspace()->orderBy('name')->get();

            if ($servers->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($servers->map(fn ($server) => [
                'id' => $server->id,
                'name' => $server->name,
                'slug' => $server->slug,
                'enabled' => $server->enabled,
                'stale' => $server->isToolDiscoveryStale(),
                'tools' => collect($server->discovered_tools ?? [])->pluck('name')->values()->toArray(),
            ])->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
