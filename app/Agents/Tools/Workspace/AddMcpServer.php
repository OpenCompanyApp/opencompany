<?php

namespace App\Agents\Tools\Workspace;

use App\Models\McpServer;
use App\Models\User;
use App\Services\Mcp\McpClient;
use App\Services\Mcp\McpToolProvider;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class AddMcpServer implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Add a new remote MCP server and auto-discover its tools.';
    }

    public function handle(Request $request): string
    {
        try {
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
            while (McpServer::forWorkspace()->where('slug', $slug)->exists()) {
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
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
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
                return "MCP server created: {$name} (ID: {$server->id}, slug: {$slug}) but tool discovery failed: {$e->getMessage()}. Server is disabled. Use discover_mcp_tools to retry.";
            }
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Server name.')
                ->required(),
            'url' => $schema
                ->string()
                ->description('MCP server endpoint URL (Streamable HTTP transport).')
                ->required(),
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
        ];
    }
}
