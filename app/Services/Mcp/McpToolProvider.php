<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class McpToolProvider implements ToolProvider
{
    /** @var array<string, McpServer>  account_alias => server */
    private array $accountServers = [];

    public function __construct(
        private McpServer $server,
    ) {}

    /**
     * Register an additional account server for this provider.
     */
    public function addAccountServer(string $account, McpServer $server): void
    {
        $this->accountServers[$account] = $server;
    }

    public function appName(): string
    {
        return 'mcp_' . $this->server->slug;
    }

    public function appMeta(): array
    {
        $toolNames = collect($this->server->discovered_tools ?? [])
            ->pluck('name')
            ->map(fn ($n) => Str::snake($n))
            ->implode(', ');

        return [
            'label' => $toolNames ?: 'no tools discovered',
            'description' => $this->server->description ?? 'MCP: ' . $this->server->name,
            'icon' => $this->server->icon,
        ];
    }

    public function tools(): array
    {
        $tools = [];
        foreach ($this->server->discovered_tools ?? [] as $mcpTool) {
            $slug = $this->toolSlug($mcpTool['name']);
            $tools[$slug] = [
                'class' => McpProxyTool::class,
                'type' => 'write',
                'name' => $mcpTool['name'],
                'description' => Str::limit($mcpTool['description'] ?? '', 120),
                'icon' => $this->server->icon,
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
        $server = $this->resolveServer($account);

        $toolSlug = $context['tool_slug'] ?? '';
        $mcpToolName = $this->mcpToolNameFromSlug($toolSlug);
        $mcpToolDef = $this->findToolDef($mcpToolName, $server);

        return new McpProxyTool(
            server: $server,
            mcpToolName: $mcpToolName,
            mcpToolDescription: $mcpToolDef['description'] ?? '',
            mcpInputSchema: $mcpToolDef['inputSchema'] ?? [],
        );
    }

    public function luaDocsPath(): ?string
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
        if ($account !== null && $account !== '' && isset($this->accountServers[$account])) {
            return $this->accountServers[$account];
        }

        return $this->server;
    }

    private function toolSlug(string $mcpToolName): string
    {
        return 'mcp_' . $this->server->slug . '__' . Str::snake($mcpToolName);
    }

    private function mcpToolNameFromSlug(string $slug): string
    {
        $prefix = 'mcp_' . $this->server->slug . '__';

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
        $tools = ($server ?? $this->server)->discovered_tools ?? [];

        foreach ($tools as $tool) {
            if (Str::snake($tool['name']) === $mcpToolName || $tool['name'] === $mcpToolName) {
                return $tool;
            }
        }

        return [];
    }
}
