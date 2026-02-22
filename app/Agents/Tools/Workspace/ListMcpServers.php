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
                return "No MCP servers configured. Use add_mcp_server to add one.";
            }

            $lines = ["MCP Servers ({$servers->count()}):"];
            foreach ($servers as $server) {
                $toolCount = count($server->discovered_tools ?? []);
                $status = $server->enabled ? 'enabled' : 'disabled';
                $stale = $server->isToolDiscoveryStale() ? ' (stale)' : '';
                $lines[] = "- {$server->name} (ID: {$server->id}, slug: {$server->slug}) — {$status}, {$toolCount} tools{$stale}";

                if (!empty($server->discovered_tools)) {
                    $toolNames = collect($server->discovered_tools)->pluck('name')->implode(', ');
                    $lines[] = "  Tools: {$toolNames}";
                }
            }

            return implode("\n", $lines);
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
