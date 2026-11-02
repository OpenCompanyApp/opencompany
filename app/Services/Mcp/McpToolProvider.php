<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

/**
 * Presents one configured MCP server as an OpenCompany integration provider.
 *
 * MCP tool names come from remote servers, while OpenCompany permissions use
 * stable local slugs. This class owns that translation and must preserve both
 * names: the local slug for catalogs/permissions and the original remote name
 * for the eventual JSON-RPC tool call.
 */
class McpToolProvider implements ToolProvider
{
    /**
     * Servers sharing this slug, grouped by OWNING workspace.
     *
     * Invariant: the tool registry is process-wide, but MCP servers are
     * workspace-owned. Catalog metadata, tool lists and calls only ever use
     * the group of the currently bound workspace; with no workspace bound the
     * provider exposes nothing. Another tenant's server is never selected.
     *
     * @var array<string, array{default: ?McpServer, accounts: array<string, McpServer>}>
     */
    private array $workspaces = [];

    public function __construct(
        private McpServer $server,
    ) {
        $this->addServer($server);
    }

    /**
     * Register another server with this slug (any workspace, any account).
     */
    public function addServer(McpServer $server): void
    {
        $workspaceId = (string) $server->workspace_id;
        $this->workspaces[$workspaceId] ??= ['default' => null, 'accounts' => []];
        $alias = (string) ($server->account_alias ?? '');
        if ($alias === '') {
            $this->workspaces[$workspaceId]['default'] = $server;
        } else {
            $this->workspaces[$workspaceId]['accounts'][$alias] = $server;
        }
    }

    /**
     * Remove a server (by id) from its workspace group.
     */
    public function removeServer(McpServer $server): void
    {
        $workspaceId = (string) $server->workspace_id;
        if (! isset($this->workspaces[$workspaceId])) {
            return;
        }
        if (($this->workspaces[$workspaceId]['default'] ?? null)?->id === $server->id) {
            $this->workspaces[$workspaceId]['default'] = null;
        }
        foreach ($this->workspaces[$workspaceId]['accounts'] as $alias => $candidate) {
            if ($candidate->id === $server->id) {
                unset($this->workspaces[$workspaceId]['accounts'][$alias]);
            }
        }
    }

    /**
     * Register an additional account server for this provider.
     */
    public function addAccountServer(string $account, McpServer $server): void
    {
        $workspaceId = (string) $server->workspace_id;
        $this->workspaces[$workspaceId] ??= ['default' => null, 'accounts' => []];
        $this->workspaces[$workspaceId]['accounts'][$account] = $server;
    }

    /**
     * Default server of the currently bound workspace, if it has one.
     */
    private function currentServer(): ?McpServer
    {
        $group = $this->currentGroup();
        if ($group === null) {
            return null;
        }

        return $group['default'] ?? (array_values($group['accounts'])[0] ?? null);
    }

    /** @return array{default: ?McpServer, accounts: array<string, McpServer>}|null */
    private function currentGroup(): ?array
    {
        if (! app()->bound('currentWorkspace')) {
            return null;
        }

        return $this->workspaces[(string) app('currentWorkspace')->id] ?? null;
    }

    public function appName(): string
    {
        return 'mcp_'.$this->server->slug;
    }

    public function appMeta(): array
    {
        $server = $this->currentServer();
        if ($server === null) {
            return ['label' => 'not configured in this workspace', 'description' => 'MCP server', 'icon' => 'ph:plugs'];
        }

        $toolNames = collect($server->discovered_tools ?? [])
            ->pluck('name')
            ->map(fn ($n) => Str::snake(str_replace('-', '_', $n)))
            ->implode(', ');

        return [
            'label' => $toolNames ?: 'no tools discovered',
            'description' => $server->description ?? 'MCP: '.$server->name,
            'icon' => $server->icon,
        ];
    }

    public function tools(): array
    {
        $server = $this->currentServer();
        if ($server === null) {
            return [];
        }

        $tools = [];
        foreach ($server->discovered_tools ?? [] as $mcpTool) {
            // Catalog keys are permission slugs, not remote MCP names. Keep the
            // original name in metadata so createTool can recover the exact
            // server-side identifier even when hyphens/case were normalized.
            $slug = $this->toolSlug($mcpTool['name']);
            $tools[$slug] = [
                'class' => McpProxyTool::class,
                // Deliberately always 'write': MCP `annotations` (readOnlyHint)
                // are claims by an external server and are not trusted. Typing a
                // tool as read would also make Code Mode treat its failures as
                // safely retryable. Every MCP call is approval-gated anyway by
                // the external-mutation floor (AgentPermissionResolver).
                'type' => 'write',
                'name' => $mcpTool['name'],
                'description' => Str::limit($mcpTool['description'] ?? '', 120),
                'icon' => $server->icon,
            ];
        }

        return $tools;
    }

    public function isIntegration(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function createTool(string $class, array $context = []): Tool
    {
        $account = $context['account'] ?? null;
        // Invariant: re-read the server at call time. The in-process provider
        // can be stale (removed/disabled by an admin in another process, or
        // auth rotated); never call with a stale, deleted or disabled row.
        $cached = $this->resolveServer($account);
        $server = McpServer::query()->whereKey($cached->id)->where('workspace_id', $cached->workspace_id)->where('enabled', true)->first()
            ?? throw new \RuntimeException("MCP server {$cached->slug} is disabled or no longer exists.");

        $toolSlug = $context['tool_slug'] ?? '';
        $mcpToolName = $this->mcpToolNameFromSlug($toolSlug);
        $mcpToolDef = $this->findToolDef($mcpToolName, $server);

        // The proxy must call the remote MCP name, not the normalized local
        // slug. Falling back to the slug keeps manually supplied contexts
        // debuggable, but discovered tools should always resolve here.
        $remoteToolName = (string) ($mcpToolDef['name'] ?? $mcpToolName);

        return new McpProxyTool(
            server: $server,
            mcpToolName: $remoteToolName,
            mcpToolDescription: $mcpToolDef['description'] ?? '',
            mcpInputSchema: $mcpToolDef['inputSchema'] ?? [],
            agent: $context['agent'] ?? null,
        );
    }

    public function scriptDocsPath(): ?string
    {
        return null;
    }

    public function credentialFields(): array
    {
        return [];
    }

    /**
     * Resolve the server for the given account alias.
     */
    private function resolveServer(?string $account): McpServer
    {
        $group = $this->currentGroup();
        if ($group !== null && $account !== null && $account !== '' && isset($group['accounts'][$account])) {
            return $group['accounts'][$account];
        }

        return $this->currentServer()
            ?? throw new \RuntimeException("MCP server {$this->server->slug} is not configured in this workspace.");
    }

    private function toolSlug(string $mcpToolName): string
    {
        // Match McpServer::getToolSlugs and McpPermissionEvaluator. If this
        // normalization changes, update all three places together or existing
        // workspace permissions will stop matching discovered tools.
        return 'mcp_'.$this->server->slug.'__'.Str::snake(str_replace('-', '_', $mcpToolName));
    }

    private function mcpToolNameFromSlug(string $slug): string
    {
        $prefix = 'mcp_'.$this->server->slug.'__';

        if (str_starts_with($slug, $prefix)) {
            return substr($slug, strlen($prefix));
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function findToolDef(string $mcpToolName, ?McpServer $server = null): array
    {
        $server ??= $this->currentServer();
        $tools = $server !== null ? ($server->discovered_tools ?? []) : [];

        foreach ($tools as $tool) {
            if (Str::snake(str_replace('-', '_', $tool['name'])) === $mcpToolName || $tool['name'] === $mcpToolName) {
                return $tool;
            }
        }

        return [];
    }
}
