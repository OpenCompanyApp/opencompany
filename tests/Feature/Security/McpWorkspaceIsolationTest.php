<?php

namespace Tests\Feature\Security;

use App\Agents\Tools\ToolRegistry;
use App\Models\McpServer;
use App\Models\Workspace;
use App\Services\Mcp\McpServerRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;
use Tests\TestCase;

/**
 * Roadmap Phase 0 P0 #13: MCP servers are workspace-owned but registered in a
 * process-wide tool registry. Two workspaces using the same server slug must
 * each see and call only their own server, even in one long-running process.
 */
class McpWorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function server(Workspace $workspace, string $tool, string $url): McpServer
    {
        return McpServer::create([
            'id' => (string) Str::uuid(), 'name' => 'GitHub', 'slug' => 'github', 'url' => $url,
            'auth_type' => 'none', 'enabled' => true, 'timeout' => 30, 'icon' => 'ph:plug',
            'workspace_id' => $workspace->id,
            'discovered_tools' => [['name' => $tool, 'description' => "{$tool} tool", 'inputSchema' => ['type' => 'object']]],
        ]);
    }

    public function test_same_slug_servers_never_leak_tools_or_routing_across_workspaces(): void
    {
        $other = Workspace::create(['name' => 'Other', 'slug' => 'other-mcp']);
        $this->server($this->workspace, 'list_issues', 'https://a.example.com/mcp');
        $this->server($other, 'delete_repo', 'https://b.example.com/mcp');

        $providers = app(ToolProviderRegistry::class);
        McpServerRegistrar::registerAll($providers);
        $registry = app(ToolRegistry::class);

        app()->instance('currentWorkspace', $this->workspace);
        $mine = $registry->getToolDefinitionBySlug('mcp_github__list_issues');
        $theirs = $registry->getToolDefinitionBySlug('mcp_github__delete_repo');
        $this->assertNotNull($mine);
        $this->assertNull($theirs, 'another workspace\'s MCP tool must not appear');
        $tool = $providers->get('mcp_github')->createTool(\App\Services\Mcp\McpProxyTool::class, ['tool_slug' => 'mcp_github__list_issues']);
        $this->assertSame('https://a.example.com/mcp', (new \ReflectionProperty($tool, 'server'))->getValue($tool)->url);

        // Same process, next job in the other workspace.
        app()->instance('currentWorkspace', $other);
        $this->assertNull($registry->getToolDefinitionBySlug('mcp_github__list_issues'));
        $this->assertNotNull($registry->getToolDefinitionBySlug('mcp_github__delete_repo'));
        $tool = $providers->get('mcp_github')->createTool(\App\Services\Mcp\McpProxyTool::class, ['tool_slug' => 'mcp_github__delete_repo']);
        $this->assertSame('https://b.example.com/mcp', (new \ReflectionProperty($tool, 'server'))->getValue($tool)->url);
    }
}
