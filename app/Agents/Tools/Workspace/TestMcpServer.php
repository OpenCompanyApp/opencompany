<?php

namespace App\Agents\Tools\Workspace;

use App\Models\McpServer;
use App\Models\User;
use App\Services\Mcp\McpClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class TestMcpServer implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Test the connection to an MCP server, either an existing one by ID or a new URL before adding.';
    }

    public function handle(Request $request): string
    {
        try {
            // Allow testing before creating (by URL)
            $url = $request['url'] ?? null;
            if ($url && !($request['serverId'] ?? null)) {
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return "Invalid URL: {$url}";
                }

                $testServer = new McpServer([
                    'url' => $url,
                    'auth_type' => $request['auth_type'] ?? 'none',
                    'auth_config' => null,
                    'timeout' => (int) ($request['timeout'] ?? 30),
                ]);

                try {
                    $client = McpClient::fromServer($testServer);
                    $result = $client->initialize();
                    $serverName = $result['serverInfo']['name'] ?? 'unknown';
                    $version = $result['serverInfo']['version'] ?? 'unknown';

                    return "Connection OK. Server: {$serverName} v{$version}. Protocol: {$result['protocolVersion']}";
                } catch (\Throwable $e) {
                    return "Connection failed: {$e->getMessage()}";
                }
            }

            $server = $this->findServer($request);
            if (is_string($server)) {
                return $server;
            }

            try {
                $client = McpClient::fromServer($server);
                $result = $client->initialize();
                $serverName = $result['serverInfo']['name'] ?? 'unknown';

                return "Connection OK to {$server->name}. Server: {$serverName}. Protocol: {$result['protocolVersion']}";
            } catch (\Throwable $e) {
                return "Connection failed for {$server->name}: {$e->getMessage()}";
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
                ->description('Server ID, slug, or name. Required when testing an existing server.'),
            'url' => $schema
                ->string()
                ->description('MCP server endpoint URL to test before adding. Used when serverId is not provided.'),
            'auth_type' => $schema
                ->string()
                ->description("Authentication type: 'none' (default), 'bearer', 'header'. Used with url."),
            'timeout' => $schema
                ->string()
                ->description('HTTP timeout in seconds (5-300). Default: 30. Used with url.'),
        ];
    }
}
