<?php

namespace App\Services\Mcp;

use App\Models\McpServer;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use OpenCompany\IntegrationCore\Contracts\ToolProvider;

class McpToolProvider implements ToolProvider
{
    public function __construct(
        private McpServer $server,
    ) {}

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
        $toolSlug = $context['tool_slug'] ?? '';
        $mcpToolName = $this->mcpToolNameFromSlug($toolSlug);
        $mcpToolDef = $this->findToolDef($mcpToolName);

        return new McpProxyTool(
            server: $this->server,
            mcpToolName: $mcpToolName,
            mcpToolDescription: $mcpToolDef['description'] ?? '',
            mcpInputSchema: $mcpToolDef['inputSchema'] ?? [],
        );
    }

    /**
     * Build tool slug: mcp_{server_slug}__{tool_name_snake}
     */
    private function toolSlug(string $mcpToolName): string
    {
        return 'mcp_' . $this->server->slug . '__' . Str::snake($mcpToolName);
    }

    /**
     * Extract MCP tool name from slug.
     */
    private function mcpToolNameFromSlug(string $slug): string
    {
        $prefix = 'mcp_' . $this->server->slug . '__';

        if (str_starts_with($slug, $prefix)) {
            return substr($slug, strlen($prefix));
        }

        return $slug;
    }

    /**
     * Find a tool definition by MCP tool name from cached discovered_tools.
     *
     * @return array<string, mixed>
     */
    private function findToolDef(string $mcpToolName): array
    {
        foreach ($this->server->discovered_tools ?? [] as $tool) {
            if (Str::snake($tool['name']) === $mcpToolName || $tool['name'] === $mcpToolName) {
                return $tool;
            }
        }

        return [];
    }
}
