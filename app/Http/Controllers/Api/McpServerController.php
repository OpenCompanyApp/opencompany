<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentPermission;
use App\Models\McpServer;
use App\Services\Mcp\McpClient;
use App\Services\Mcp\McpServerRegistrar;
use App\Services\Mcp\McpToolProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class McpServerController extends Controller
{
    /**
     * List all MCP servers.
     */
    public function index(): JsonResponse
    {
        $servers = McpServer::forWorkspace()->orderBy('name')->get();

        return response()->json($servers->map(fn (McpServer $server) => [
            'id' => $server->id,
            'name' => $server->name,
            'slug' => $server->slug,
            'enabled' => $server->enabled,
            'authType' => $server->auth_type,
            'maskedAuth' => $server->getMaskedAuthValue(),
            'icon' => $server->icon,
            'description' => $server->description,
            'timeout' => $server->timeout,
            'toolCount' => count($server->discovered_tools ?? []),
            'tools' => collect($server->discovered_tools ?? [])->map(fn ($t) => [
                'name' => $t['name'],
                'description' => Str::limit($t['description'] ?? '', 120),
            ]),
            'toolsDiscoveredAt' => $server->tools_discovered_at?->toIso8601String(),
            'isStale' => $server->isToolDiscoveryStale(),
            'createdAt' => $server->created_at?->toIso8601String(),
        ]));
    }

    /**
     * Create a new MCP server and auto-discover tools.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string|url',
            'auth_type' => 'nullable|string|in:none,bearer,header',
            'auth_config' => 'nullable|array',
            'timeout' => 'nullable|integer|min:5|max:300',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $slug = Str::slug($request->input('name'), '_');

        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (McpServer::forWorkspace()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $counter++;
        }

        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $request->input('name'),
            'slug' => $slug,
            'url' => $request->input('url'),
            'auth_type' => $request->input('auth_type', 'none'),
            'auth_config' => $request->input('auth_config'),
            'timeout' => $request->input('timeout', 30),
            'icon' => $request->input('icon', 'ph:plug'),
            'description' => $request->input('description'),
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

            // Register the new provider in the registry
            $registry = app(ToolProviderRegistry::class);
            $registry->register(new McpToolProvider($server));
        } catch (\Throwable $e) {
            // Server created but discovery failed -- user can retry via discover endpoint
            return response()->json([
                'server' => $this->formatServer($server),
                'warning' => 'Server created but tool discovery failed: ' . $e->getMessage(),
            ], 201);
        }

        return response()->json([
            'server' => $this->formatServer($server->fresh()),
        ], 201);
    }

    /**
     * Get MCP server details.
     */
    public function show(string $id): JsonResponse
    {
        $server = McpServer::forWorkspace()->findOrFail($id);

        return response()->json($this->formatServer($server));
    }

    /**
     * Update MCP server configuration.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $server = McpServer::forWorkspace()->findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'url' => 'nullable|string|url',
            'auth_type' => 'nullable|string|in:none,bearer,header',
            'auth_config' => 'nullable|array',
            'timeout' => 'nullable|integer|min:5|max:300',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'enabled' => 'nullable|boolean',
        ]);

        $updates = array_filter($request->only([
            'name', 'url', 'auth_type', 'auth_config', 'timeout', 'icon', 'description', 'enabled',
        ]), fn ($v) => $v !== null);

        // Skip masked auth values
        if (isset($updates['auth_config'])) {
            foreach ($updates['auth_config'] as $key => $value) {
                if (is_string($value) && str_contains($value, '*')) {
                    $existing = $server->auth_config ?? [];
                    $updates['auth_config'][$key] = $existing[$key] ?? $value;
                }
            }
        }

        $server->update($updates);

        return response()->json($this->formatServer($server->fresh()));
    }

    /**
     * Delete an MCP server and clean up permissions.
     */
    public function destroy(string $id): JsonResponse
    {
        $server = McpServer::forWorkspace()->findOrFail($id);
        $appName = 'mcp_' . $server->slug;

        // Clean up agent permissions for this MCP server
        AgentPermission::where('scope_type', 'integration')
            ->where('scope_key', $appName)
            ->delete();

        // Clean up tool-level permissions
        $toolSlugs = $server->getToolSlugs();
        if (!empty($toolSlugs)) {
            AgentPermission::where('scope_type', 'tool')
                ->whereIn('scope_key', $toolSlugs)
                ->delete();
        }

        $server->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Test connection to MCP server.
     */
    public function testConnection(Request $request, string $id): JsonResponse
    {
        $server = McpServer::forWorkspace()->findOrFail($id);

        // Allow overriding URL/auth for testing before saving
        $url = $request->input('url', $server->url);
        $authType = $request->input('auth_type', $server->auth_type);
        $authConfig = $request->input('auth_config', $server->auth_config);
        $timeout = $request->input('timeout', $server->timeout);

        // Build a temporary server for testing
        $testServer = new McpServer([
            'url' => $url,
            'auth_type' => $authType,
            'auth_config' => $authConfig,
            'timeout' => $timeout,
        ]);

        try {
            $client = McpClient::fromServer($testServer);
            $result = $client->initialize();

            return response()->json([
                'success' => true,
                'serverInfo' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Refresh tool discovery for an MCP server.
     */
    public function discoverTools(string $id): JsonResponse
    {
        $server = McpServer::forWorkspace()->findOrFail($id);

        try {
            $client = McpClient::fromServer($server);
            $tools = $client->listTools();

            $server->update([
                'discovered_tools' => $tools,
                'tools_discovered_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'toolCount' => count($tools),
                'tools' => collect($tools)->map(fn ($t) => [
                    'name' => $t['name'],
                    'description' => Str::limit($t['description'] ?? '', 120),
                ]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatServer(McpServer $server): array
    {
        return [
            'id' => $server->id,
            'name' => $server->name,
            'slug' => $server->slug,
            'url' => $server->url,
            'enabled' => $server->enabled,
            'auth_type' => $server->auth_type,
            'maskedAuth' => $server->getMaskedAuthValue(),
            'icon' => $server->icon,
            'description' => $server->description,
            'timeout' => $server->timeout,
            'toolCount' => count($server->discovered_tools ?? []),
            'tools' => collect($server->discovered_tools ?? [])->map(fn ($t) => [
                'name' => $t['name'],
                'description' => Str::limit($t['description'] ?? '', 120),
            ]),
            'toolsDiscoveredAt' => $server->tools_discovered_at?->toIso8601String(),
            'isStale' => $server->isToolDiscoveryStale(),
            'createdAt' => $server->created_at?->toIso8601String(),
        ];
    }
}
