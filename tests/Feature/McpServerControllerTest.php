<?php

namespace Tests\Feature;

use App\Models\AgentPermission;
use App\Models\McpServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpServerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // --- Index ---

    public function test_index_returns_empty_list(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/mcp-servers');

        $response->assertOk()
            ->assertJsonCount(0);
    }

    public function test_index_returns_servers_ordered_by_name(): void
    {
        McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Zebra Server',
            'slug' => 'zebra',
            'url' => 'https://zebra.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Alpha Server',
            'slug' => 'alpha',
            'url' => 'https://alpha.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/mcp-servers');

        $response->assertOk()
            ->assertJsonCount(2);

        $data = $response->json();
        $this->assertEquals('Alpha Server', $data[0]['name']);
        $this->assertEquals('Zebra Server', $data[1]['name']);
    }

    // --- Store ---

    public function test_store_creates_server_and_discovers_tools(): void
    {
        Http::fake([
            '*' => Http::sequence()
                // initialize response
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'protocolVersion' => '2025-03-26',
                        'serverInfo' => ['name' => 'test-server', 'version' => '1.0'],
                        'capabilities' => ['tools' => []],
                    ],
                ])
                // tools/list response
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 2,
                    'result' => [
                        'tools' => [
                            [
                                'name' => 'search',
                                'description' => 'Search the web',
                                'inputSchema' => ['type' => 'object', 'properties' => []],
                            ],
                        ],
                    ],
                ]),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/mcp-servers', [
            'name' => 'My MCP Server',
            'url' => 'https://mcp.example.com/api',
            'auth_type' => 'bearer',
            'auth_config' => ['token' => 'secret-token'],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('mcp_servers', [
            'name' => 'My MCP Server',
            'slug' => 'my_mcp_server',
            'auth_type' => 'bearer',
        ]);

        $server = McpServer::where('slug', 'my_mcp_server')->first();
        $this->assertTrue($server->enabled);
        $this->assertNotNull($server->discovered_tools);
        $this->assertCount(1, $server->discovered_tools);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/mcp-servers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'url']);
    }

    public function test_store_validates_url_format(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/mcp-servers', [
            'name' => 'Test',
            'url' => 'not-a-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_store_creates_server_even_when_discovery_fails(): void
    {
        Http::fake([
            '*' => Http::response('Connection refused', 500),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/mcp-servers', [
            'name' => 'Failing Server',
            'url' => 'https://failing.example.com',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('mcp_servers', [
            'name' => 'Failing Server',
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('warning', $data);
    }

    public function test_store_generates_unique_slug(): void
    {
        McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Server',
            'slug' => 'test_server',
            'url' => 'https://test.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        Http::fake([
            '*' => Http::response('Connection refused', 500),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/mcp-servers', [
            'name' => 'Test Server',
            'url' => 'https://test2.example.com',
        ]);

        $response->assertStatus(201);

        // Should have two servers now with different slugs
        $this->assertEquals(2, McpServer::where('name', 'Test Server')->count());
        $slugs = McpServer::where('name', 'Test Server')->pluck('slug')->sort()->values()->toArray();
        $this->assertEquals(['test_server', 'test_server_1'], $slugs);
    }

    // --- Show ---

    public function test_show_returns_server_details(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Show Test',
            'slug' => 'show_test',
            'url' => 'https://show.example.com',
            'auth_type' => 'bearer',
            'auth_config' => ['token' => 'secret-token-12345678'],
            'enabled' => true,
            'timeout' => 30,
            'icon' => 'ph:globe',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/mcp-servers/{$server->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Show Test',
                'slug' => 'show_test',
                'icon' => 'ph:globe',
            ]);

        // Auth should be masked
        $data = $response->json();
        $this->assertNotEquals('secret-token-12345678', $data['maskedAuth']);
        $this->assertStringContainsString('***', $data['maskedAuth']);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/mcp-servers/nonexistent-id');

        $response->assertNotFound();
    }

    // --- Update ---

    public function test_update_modifies_server_fields(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Original Name',
            'slug' => 'original',
            'url' => 'https://original.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/mcp-servers/{$server->id}", [
            'name' => 'Updated Name',
            'timeout' => 60,
            'description' => 'Updated description',
        ]);

        $response->assertOk();

        $server->refresh();
        $this->assertEquals('Updated Name', $server->name);
        $this->assertEquals(60, $server->timeout);
        $this->assertEquals('Updated description', $server->description);
    }

    public function test_update_can_disable_server(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Active Server',
            'slug' => 'active',
            'url' => 'https://active.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/mcp-servers/{$server->id}", [
            'enabled' => false,
        ]);

        $response->assertOk();
        $server->refresh();
        $this->assertFalse($server->enabled);
    }

    // --- Destroy ---

    public function test_destroy_deletes_server(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'To Delete',
            'slug' => 'to_delete',
            'url' => 'https://delete.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/mcp-servers/{$server->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseMissing('mcp_servers', ['id' => $server->id]);
    }

    public function test_destroy_cleans_up_permissions(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Perm Server',
            'slug' => 'perm_server',
            'url' => 'https://perm.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
            'discovered_tools' => [
                ['name' => 'tool_one', 'description' => 'First tool'],
            ],
        ]);

        $agent = User::factory()->create(['type' => 'agent']);

        // Create integration-level permission
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $agent->id,
            'scope_type' => 'integration',
            'scope_key' => 'mcp_perm_server',
            'permission' => 'allow',
        ]);

        // Create tool-level permission
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'mcp_perm_server__tool_one',
            'permission' => 'deny',
        ]);

        $this->actingAs($this->user)->deleteJson("/api/mcp-servers/{$server->id}");

        $this->assertDatabaseMissing('agent_permissions', [
            'scope_key' => 'mcp_perm_server',
        ]);
        $this->assertDatabaseMissing('agent_permissions', [
            'scope_key' => 'mcp_perm_server__tool_one',
        ]);
    }

    // --- Test Connection ---

    public function test_test_connection_succeeds(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Conn',
            'slug' => 'test_conn',
            'url' => 'https://test-conn.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'protocolVersion' => '2025-03-26',
                    'serverInfo' => ['name' => 'test', 'version' => '1.0'],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/mcp-servers/{$server->id}/test");

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    public function test_test_connection_reports_failure(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Fail Conn',
            'slug' => 'fail_conn',
            'url' => 'https://fail-conn.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        Http::fake([
            '*' => Http::response('Connection refused', 500),
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/mcp-servers/{$server->id}/test");

        $response->assertStatus(400)
            ->assertJsonFragment(['success' => false]);
    }

    // --- Discover Tools ---

    public function test_discover_tools_refreshes_cache(): void
    {
        $server = McpServer::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Discover Test',
            'slug' => 'discover_test',
            'url' => 'https://discover.example.com',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
            'discovered_tools' => [],
        ]);

        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'tools' => [
                        ['name' => 'new_tool', 'description' => 'Newly discovered'],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/mcp-servers/{$server->id}/discover");

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'toolCount' => 1]);

        $server->refresh();
        $this->assertCount(1, $server->discovered_tools);
        $this->assertEquals('new_tool', $server->discovered_tools[0]['name']);
        $this->assertNotNull($server->tools_discovered_at);
    }
}
