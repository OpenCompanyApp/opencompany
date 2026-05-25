<?php

namespace App\Domain\Integrations\Application;

use App\Models\AgentPermission;
use App\Models\McpServer;
use App\Services\Mcp\McpClient;
use App\Services\Mcp\McpToolProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Workspace-facing MCP server management use cases.
 *
 * The MCP client/proxy/runtime classes remain infrastructure adapters. This
 * application service owns OpenCompany's workspace policy around local MCP
 * server records: stable slugs, masked secret preservation, discovery cache
 * refreshes, and cleanup of permission rows that reference generated MCP tool
 * slugs.
 */
class ManageMcpServers
{
    /**
     * Return workspace MCP servers in the API shape used by the settings UI.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function list(): Collection
    {
        return McpServer::forWorkspace()
            ->orderBy('name')
            ->get()
            ->map(fn (McpServer $server) => $this->format($server));
    }

    /**
     * Create a workspace MCP server and attempt initial tool discovery.
     *
     * @param  array<string, mixed>  $data
     * @return array{server: array<string, mixed>, warning?: string}
     */
    public function create(array $data): array
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'url' => $data['url'],
            'auth_type' => $data['auth_type'] ?? 'none',
            'auth_config' => $data['auth_config'] ?? null,
            'timeout' => $data['timeout'] ?? 30,
            'icon' => $data['icon'] ?? 'ph:plug',
            'description' => $data['description'] ?? null,
            'enabled' => false,
        ]);

        try {
            $this->discoverInitial($server);
            app(ToolProviderRegistry::class)->register(new McpToolProvider($server->fresh()));
        } catch (\Throwable $e) {
            return [
                'server' => $this->format($server),
                'warning' => 'Server created but tool discovery failed: '.$e->getMessage(),
            ];
        }

        return ['server' => $this->format($server->fresh())];
    }

    /**
     * Return one workspace MCP server in API shape.
     *
     * @return array<string, mixed>
     */
    public function show(string $id): array
    {
        return $this->format(McpServer::forWorkspace()->findOrFail($id));
    }

    /**
     * Update a workspace MCP server while preserving masked secrets.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $id, array $data): array
    {
        $server = McpServer::forWorkspace()->findOrFail($id);
        $updates = array_filter($data, fn (mixed $value) => $value !== null);

        if (isset($updates['auth_config']) && is_array($updates['auth_config'])) {
            foreach ($updates['auth_config'] as $key => $value) {
                if (is_string($value) && str_contains($value, '*')) {
                    $existing = $server->auth_config ?? [];
                    $updates['auth_config'][$key] = $existing[$key] ?? $value;
                }
            }
        }

        $server->update($updates);

        return $this->format($server->fresh());
    }

    /**
     * Delete a workspace MCP server and remove generated permission references.
     */
    public function delete(string $id): void
    {
        $server = McpServer::forWorkspace()->findOrFail($id);
        $appName = 'mcp_'.$server->slug;

        AgentPermission::where('scope_type', 'integration')
            ->where('scope_key', $appName)
            ->whereHas('agent', fn ($query) => $query->where('workspace_id', $server->workspace_id))
            ->delete();

        $toolSlugs = $server->getToolSlugs();
        if ($toolSlugs !== []) {
            AgentPermission::where('scope_type', 'tool')
                ->whereIn('scope_key', $toolSlugs)
                ->whereHas('agent', fn ($query) => $query->where('workspace_id', $server->workspace_id))
                ->delete();
        }

        $server->delete();
    }

    /**
     * Test an MCP connection without mutating the saved server.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function testConnection(string $id, array $overrides = []): array
    {
        $server = McpServer::forWorkspace()->findOrFail($id);
        $testServer = new McpServer([
            'url' => $overrides['url'] ?? $server->url,
            'auth_type' => $overrides['auth_type'] ?? $server->auth_type,
            'auth_config' => $overrides['auth_config'] ?? $server->auth_config,
            'timeout' => $overrides['timeout'] ?? $server->timeout,
        ]);

        try {
            return [
                'success' => true,
                'serverInfo' => McpClient::fromServer($testServer)->initialize(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test a new MCP server definition before it has been persisted.
     *
     * This deliberately avoids creating a workspace record or writing discovered
     * tools. The caller owns validating the shape before passing it in.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function testUnsavedConnection(array $config): array
    {
        $testServer = new McpServer([
            'url' => $config['url'],
            'auth_type' => $config['auth_type'] ?? 'none',
            'auth_config' => $config['auth_config'] ?? null,
            'timeout' => $config['timeout'] ?? 30,
        ]);

        try {
            return [
                'success' => true,
                'serverInfo' => McpClient::fromServer($testServer)->initialize(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh one server's discovered tool cache.
     *
     * @return array<string, mixed>
     */
    public function refreshTools(string $id): array
    {
        $server = McpServer::forWorkspace()->findOrFail($id);
        $tools = $this->refreshDiscoveryCache($server);

        return [
            'success' => true,
            'toolCount' => count($tools),
            'tools' => collect($tools)->map(fn (array $tool) => [
                'name' => $tool['name'],
                'description' => Str::limit($tool['description'] ?? '', 120),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function format(McpServer $server): array
    {
        return [
            'id' => $server->id,
            'name' => $server->name,
            'slug' => $server->slug,
            'url' => $server->url,
            'enabled' => $server->enabled,
            'authType' => $server->auth_type,
            'auth_type' => $server->auth_type,
            'maskedAuth' => $server->getMaskedAuthValue(),
            'icon' => $server->icon,
            'description' => $server->description,
            'timeout' => $server->timeout,
            'toolCount' => count($server->discovered_tools ?? []),
            'tools' => collect($server->discovered_tools ?? [])->map(fn (array $tool) => [
                'name' => $tool['name'],
                'description' => Str::limit($tool['description'] ?? '', 120),
            ]),
            'toolsDiscoveredAt' => $server->tools_discovered_at?->toIso8601String(),
            'isStale' => $server->isToolDiscoveryStale(),
            'createdAt' => $server->created_at?->toIso8601String(),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name, '_');
        $slug = $baseSlug;
        $counter = 1;

        while (McpServer::forWorkspace()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'_'.$counter++;
        }

        return $slug;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function discoverInitial(McpServer $server): array
    {
        $client = McpClient::fromServer($server);
        $serverInfo = $client->initialize();
        $tools = $client->listTools();

        $server->update([
            'server_info' => $serverInfo,
            'discovered_tools' => $tools,
            'tools_discovered_at' => now(),
            'enabled' => true,
        ]);

        return $tools;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function refreshDiscoveryCache(McpServer $server): array
    {
        $tools = McpClient::fromServer($server)->listTools();

        $server->update([
            'discovered_tools' => $tools,
            'tools_discovered_at' => now(),
        ]);

        return $tools;
    }
}
