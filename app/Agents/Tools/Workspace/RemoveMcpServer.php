<?php

namespace App\Agents\Tools\Workspace;

use App\Models\AgentPermission;
use App\Models\McpServer;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RemoveMcpServer implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Remove an MCP server and clean up associated permissions.';
    }

    public function handle(Request $request): string
    {
        try {
            $server = $this->findServer($request);
            if (is_string($server)) {
                return $server;
            }

            $name = $server->name;
            $appName = 'mcp_' . $server->slug;

            // Clean up permissions
            AgentPermission::where('scope_type', 'integration')
                ->where('scope_key', $appName)
                ->delete();

            $toolSlugs = $server->getToolSlugs();
            if (!empty($toolSlugs)) {
                AgentPermission::where('scope_type', 'tool')
                    ->whereIn('scope_key', $toolSlugs)
                    ->delete();
            }

            $server->delete();

            return "MCP server removed: {$name}. Associated permissions cleaned up.";
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
