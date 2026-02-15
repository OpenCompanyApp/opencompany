<?php

namespace Tests\Feature;

use App\Models\McpServer;
use App\Services\Mcp\McpProxyTool;
use App\Services\Mcp\McpToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpToolProviderTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    private McpToolProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Brave Search',
            'slug' => 'brave_search',
            'url' => 'https://mcp.brave.com',
            'auth_type' => 'bearer',
            'auth_config' => ['token' => 'test-token'],
            'enabled' => true,
            'timeout' => 30,
            'icon' => 'ph:magnifying-glass',
            'description' => 'Brave web search API',
            'workspace_id' => $this->workspace->id,
            'discovered_tools' => [
                [
                    'name' => 'brave_web_search',
                    'description' => 'Search the web using Brave',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search query'],
                            'count' => ['type' => 'integer', 'description' => 'Results count'],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'brave_local_search',
                    'description' => 'Search for local businesses',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string'],
                            'location' => ['type' => 'string'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ]);

        $this->provider = new McpToolProvider($this->server);
    }

    public function test_app_name_returns_prefixed_slug(): void
    {
        $this->assertEquals('mcp_brave_search', $this->provider->appName());
    }

    public function test_app_meta_contains_label_and_description(): void
    {
        $meta = $this->provider->appMeta();

        $this->assertArrayHasKey('label', $meta);
        $this->assertArrayHasKey('description', $meta);
        $this->assertArrayHasKey('icon', $meta);
        $this->assertEquals('ph:magnifying-glass', $meta['icon']);
        $this->assertStringContainsString('brave_web_search', $meta['label']);
    }

    public function test_tools_returns_slug_to_meta_map(): void
    {
        $tools = $this->provider->tools();

        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('mcp_brave_search__brave_web_search', $tools);
        $this->assertArrayHasKey('mcp_brave_search__brave_local_search', $tools);
    }

    public function test_tools_meta_has_correct_structure(): void
    {
        $tools = $this->provider->tools();
        $tool = $tools['mcp_brave_search__brave_web_search'];

        $this->assertEquals(McpProxyTool::class, $tool['class']);
        $this->assertEquals('write', $tool['type']);
        $this->assertEquals('brave_web_search', $tool['name']);
        $this->assertStringContainsString('Search the web', $tool['description']);
    }

    public function test_is_integration_returns_true(): void
    {
        $this->assertTrue($this->provider->isIntegration());
    }

    public function test_create_tool_returns_mcp_proxy_tool(): void
    {
        $tool = $this->provider->createTool(McpProxyTool::class, [
            'tool_slug' => 'mcp_brave_search__brave_web_search',
        ]);

        $this->assertInstanceOf(McpProxyTool::class, $tool);
        $this->assertEquals('mcp_brave_search__brave_web_search', $tool->name());
        $this->assertEquals('Search the web using Brave', $tool->description());
    }

    public function test_create_tool_with_different_slug(): void
    {
        $tool = $this->provider->createTool(McpProxyTool::class, [
            'tool_slug' => 'mcp_brave_search__brave_local_search',
        ]);

        $this->assertInstanceOf(McpProxyTool::class, $tool);
        $this->assertEquals('mcp_brave_search__brave_local_search', $tool->name());
        $this->assertEquals('Search for local businesses', $tool->description());
    }

    public function test_tools_empty_when_no_discovered_tools(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Empty Server',
            'slug' => 'empty_server',
            'url' => 'https://empty.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
            'workspace_id' => $this->workspace->id,
            'discovered_tools' => null,
        ]);

        $provider = new McpToolProvider($server);
        $tools = $provider->tools();

        $this->assertEmpty($tools);
    }
}
