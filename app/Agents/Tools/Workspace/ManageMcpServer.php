<?php

namespace App\Agents\Tools\Workspace;

use App\Models\AgentPermission;
use App\Models\McpServer;
use App\Services\Mcp\McpClient;
use App\Services\Mcp\McpToolProvider;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class ManageMcpServer implements Tool
{

    public function description(): string
    {
        return 'List, add, update, remove, and test remote MCP servers. Discover their tools to make them available to agents.';
    }

    public function handle(Request $request): string
    {
        try {
            return match ($request['action']) {
                'list' => $this->list(),
                'add' => $this->add($request),
                'update' => $this->update($request),
                'remove' => $this->remove($request),
                'test' => $this->test($request),
                'discover' => $this->discover($request),
                default => "Unknown action: {$request['action']}. Use: list, add, update, remove, test, discover",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function list(): string
    {
        $servers = McpServer::orderBy('name')->get();

        if ($servers->isEmpty()) {
            return "No MCP servers configured. Use action 'add' to add one.";
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
    }

    private function add(Request $request): string
    {
        $name = $request['name'] ?? null;
        $url = $request['url'] ?? null;

        if (!$name || !$url) {
            return "Both 'name' and 'url' are required to add an MCP server.";
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return "Invalid URL: {$url}";
        }

        $slug = Str::slug($name, '_');
        $baseSlug = $slug;
        $counter = 1;
        while (McpServer::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $counter++;
        }

        $authType = $request['auth_type'] ?? 'none';
        $authConfig = null;
        if ($authType === 'bearer' && !empty($request['auth_token'])) {
            $authConfig = ['token' => $request['auth_token']];
        } elseif ($authType === 'header' && !empty($request['header_name'])) {
            $authConfig = [
                'header_name' => $request['header_name'],
                'header_value' => $request['auth_token'] ?? '',
            ];
        }

        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'slug' => $slug,
            'url' => $url,
            'auth_type' => $authType,
            'auth_config' => $authConfig,
            'timeout' => (int) ($request['timeout'] ?? 30),
            'icon' => $request['icon'] ?? 'ph:plug',
            'description' => $request['description'] ?? null,
            'enabled' => false,
        ]);

        // Auto-discover tools
        try {
            $client = McpClient::fromServer($server);
            $serverInfo = $client->initialize();
            $tools = $client->listTools();

            $server->update([
                'server_info' => $serverInfo,
                'discovered_tools' => $tools,
                'tools_discovered_at' => now(),
                'enabled' => true,
            ]);

            $registry = app(ToolProviderRegistry::class);
            $registry->register(new McpToolProvider($server));

            $toolCount = count($tools);
            $toolNames = collect($tools)->pluck('name')->implode(', ');

            return "MCP server added: {$name} (ID: {$server->id}, slug: {$slug}). Discovered {$toolCount} tools: {$toolNames}. Server enabled.";
        } catch (\Throwable $e) {
            return "MCP server created: {$name} (ID: {$server->id}, slug: {$slug}) but tool discovery failed: {$e->getMessage()}. Server is disabled. Use action 'discover' to retry.";
        }
    }

    private function update(Request $request): string
    {
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
    }

    private function remove(Request $request): string
    {
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
    }

    private function test(Request $request): string
    {
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
    }

    private function discover(Request $request): string
    {
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
    }

    /**
     * Find an MCP server by serverId param (accepts ID, slug, or name).
     */
    private function findServer(Request $request): McpServer|string
    {
        $identifier = $request['serverId'] ?? null;
        if (!$identifier) {
            return "serverId is required. Use action 'list' to see available servers.";
        }

        // Try by ID first
        $server = McpServer::find($identifier);
        if ($server) {
            return $server;
        }

        // Try by slug
        $server = McpServer::where('slug', $identifier)->first();
        if ($server) {
            return $server;
        }

        // Try by name (case-insensitive)
        $server = McpServer::whereRaw('LOWER(name) = ?', [strtolower($identifier)])->first();
        if ($server) {
            return $server;
        }

        return "MCP server not found: '{$identifier}'. Use action 'list' to see available servers.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'list' (show all servers), 'add' (create new), 'update' (modify existing), 'remove' (delete), 'test' (check connection), 'discover' (refresh tools).")
                ->required(),
            'serverId' => $schema
                ->string()
                ->description('Server ID, slug, or name. Required for update, remove, test (existing), discover.'),
            'name' => $schema
                ->string()
                ->description('Server name. Required for add.'),
            'url' => $schema
                ->string()
                ->description('MCP server endpoint URL (Streamable HTTP transport). Required for add. Also used by test to check a URL before adding.'),
            'auth_type' => $schema
                ->string()
                ->description("Authentication type: 'none' (default), 'bearer', 'header'."),
            'auth_token' => $schema
                ->string()
                ->description('Bearer token or header value for authentication.'),
            'header_name' => $schema
                ->string()
                ->description("Custom header name when auth_type is 'header' (e.g., 'X-API-Key')."),
            'icon' => $schema
                ->string()
                ->description("Phosphor icon name (e.g., 'ph:plug', 'ph:book-open'). Default: 'ph:plug'."),
            'description' => $schema
                ->string()
                ->description('Short description of what the MCP server does.'),
            'timeout' => $schema
                ->string()
                ->description('HTTP timeout in seconds (5-300). Default: 30.'),
            'enabled' => $schema
                ->string()
                ->description("'true' or 'false' to enable/disable the server. For update only."),
        ];
    }
}
