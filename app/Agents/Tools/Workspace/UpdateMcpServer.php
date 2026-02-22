<?php

namespace App\Agents\Tools\Workspace;

use App\Models\McpServer;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateMcpServer implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update an existing MCP server configuration (name, URL, icon, timeout, enabled status).';
    }

    public function handle(Request $request): string
    {
        try {
            $server = $this->findServer($request);
            if (is_string($server)) {
                return $server;
            }

            $updates = [];

            if (!empty($request['name'])) {
                $updates['name'] = $request['name'];
            }
            if (!empty($request['url'])) {
                if (!filter_var($request['url'], FILTER_VALIDATE_URL)) {
                    return "Invalid URL: {$request['url']}";
                }
                $updates['url'] = $request['url'];
            }
            if (!empty($request['icon'])) {
                $updates['icon'] = $request['icon'];
            }
            if (!empty($request['description'])) {
                $updates['description'] = $request['description'];
            }
            if (isset($request['timeout'])) {
                $updates['timeout'] = max(5, min(300, (int) $request['timeout']));
            }
            if (isset($request['enabled'])) {
                $updates['enabled'] = filter_var($request['enabled'], FILTER_VALIDATE_BOOLEAN);
            }

            if (empty($updates)) {
                return 'No fields to update. Provide at least one of: name, url, icon, description, timeout, enabled.';
            }

            $server->update($updates);

            return "MCP server updated: {$server->name} (ID: {$server->id}). Updated fields: " . implode(', ', array_keys($updates)) . '.';
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
        if ($server) return $server;

        $server = McpServer::forWorkspace()->where('slug', $identifier)->first();
        if ($server) return $server;

        $server = McpServer::forWorkspace()->whereRaw('LOWER(name) = ?', [strtolower($identifier)])->first();
        if ($server) return $server;

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
            'name' => $schema
                ->string()
                ->description('New server name.'),
            'url' => $schema
                ->string()
                ->description('New MCP server endpoint URL.'),
            'icon' => $schema
                ->string()
                ->description("Phosphor icon name (e.g., 'ph:plug', 'ph:book-open')."),
            'description' => $schema
                ->string()
                ->description('Short description of what the MCP server does.'),
            'timeout' => $schema
                ->string()
                ->description('HTTP timeout in seconds (5-300).'),
            'enabled' => $schema
                ->string()
                ->description("'true' or 'false' to enable/disable the server."),
        ];
    }
}
