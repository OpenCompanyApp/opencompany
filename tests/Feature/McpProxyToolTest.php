<?php

namespace Tests\Feature;

use App\Models\McpServer;
use App\Services\Mcp\McpProxyTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class McpProxyToolTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Server',
            'slug' => 'test_server',
            'url' => 'https://mcp.example.com/api',
            'auth_type' => 'bearer',
            'auth_config' => ['token' => 'test-token-123'],
            'enabled' => true,
            'timeout' => 30,
            'icon' => 'ph:plug',
            'discovered_tools' => [
                [
                    'name' => 'web_search',
                    'description' => 'Search the web',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search query',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ]);
    }

    public function test_name_returns_prefixed_slug(): void
    {
        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'web_search',
            mcpToolDescription: 'Search the web',
            mcpInputSchema: [],
        );

        $this->assertEquals('mcp_test_server__web_search', $tool->name());
    }

    public function test_description_returns_mcp_tool_description(): void
    {
        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'web_search',
            mcpToolDescription: 'Search the web for information',
            mcpInputSchema: [],
        );

        $this->assertEquals('Search the web for information', $tool->description());
    }

    public function test_schema_translates_input_schema(): void
    {
        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'web_search',
            mcpToolDescription: 'Search the web',
            mcpInputSchema: [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query'],
                    'count' => ['type' => 'integer', 'description' => 'Result count'],
                ],
                'required' => ['query'],
            ],
        );

        $schema = $tool->schema(new JsonSchemaTypeFactory);

        $this->assertArrayHasKey('query', $schema);
        $this->assertArrayHasKey('count', $schema);
    }

    public function test_handle_calls_mcp_server_and_returns_text(): void
    {
        Http::fake([
            'mcp.example.com/*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'content' => [
                        ['type' => 'text', 'text' => 'Here are the search results...'],
                    ],
                ],
            ]),
        ]);

        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'web_search',
            mcpToolDescription: 'Search the web',
            mcpInputSchema: [],
        );

        $request = new Request(['query' => 'Laravel testing']);
        $result = $tool->handle($request);

        $this->assertEquals('Here are the search results...', $result);
    }

    public function test_handle_returns_error_message_on_mcp_error(): void
    {
        Http::fake([
            'mcp.example.com/*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'isError' => true,
                    'content' => [
                        ['type' => 'text', 'text' => 'Rate limit exceeded'],
                    ],
                ],
            ]),
        ]);

        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'web_search',
            mcpToolDescription: 'Search the web',
            mcpInputSchema: [],
        );

        $request = new Request(['query' => 'test']);
        $result = $tool->handle($request);

        $this->assertStringContainsString('MCP Error', $result);
        $this->assertStringContainsString('Rate limit exceeded', $result);
    }

    public function test_handle_returns_error_on_http_failure(): void
    {
        Http::fake([
            'mcp.example.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'web_search',
            mcpToolDescription: 'Search the web',
            mcpInputSchema: [],
        );

        $request = new Request(['query' => 'test']);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Error calling MCP tool', $result);
    }

    public function test_handle_concatenates_multiple_text_content(): void
    {
        Http::fake([
            'mcp.example.com/*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'content' => [
                        ['type' => 'text', 'text' => 'Line 1'],
                        ['type' => 'text', 'text' => 'Line 2'],
                    ],
                ],
            ]),
        ]);

        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'web_search',
            mcpToolDescription: 'Search',
            mcpInputSchema: [],
        );

        $request = new Request(['query' => 'test']);
        $result = $tool->handle($request);

        $this->assertEquals("Line 1\nLine 2", $result);
    }

    public function test_name_handles_hyphenated_tool_names(): void
    {
        $tool = new McpProxyTool(
            server: $this->server,
            mcpToolName: 'brave-web-search',
            mcpToolDescription: 'Brave search',
            mcpInputSchema: [],
        );

        $this->assertEquals('mcp_test_server__brave_web_search', $tool->name());
    }
}
