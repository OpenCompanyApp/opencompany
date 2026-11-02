<?php

namespace Tests\Feature\AgentSafety;

use App\Agents\Tools\Workspace\AddMcpServer;
use App\Domain\Web\Safety\WebRequestGuard;
use App\Models\McpServer;
use App\Models\User;
use App\Services\Mcp\McpClient;
use App\Services\Mcp\McpUrlNotAllowed;
use App\Services\Mcp\McpUrlPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * MCP SSRF regressions: MCP server URLs must not reach loopback, private,
 * or cloud-metadata addresses unless the operator allowlisted them, and the
 * check must run before every request (not only when the server is saved).
 */
class McpEgressGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a guard whose DNS answers are scripted per host.
     *
     * @param  array<string, list<string>>  $dns
     */
    private function fakeDns(array $dns): void
    {
        $this->app->instance(WebRequestGuard::class, new class($dns) extends WebRequestGuard
        {
            /** @param array<string, list<string>> $dns */
            public function __construct(private array $dns)
            {
                parent::__construct();
            }

            protected function resolveIpAddresses(string $host): array
            {
                return $this->dns[$host] ?? [];
            }
        });
    }

    public function test_blocks_loopback_private_and_metadata_targets_by_default(): void
    {
        $this->fakeDns(['rebind.example.com' => ['93.184.216.34', '10.0.0.5']]);
        $policy = app(McpUrlPolicy::class);

        foreach ([
            'http://127.0.0.1:8080/mcp',
            'http://localhost/mcp',
            'http://10.1.2.3/mcp',
            'http://192.168.1.10/mcp',
            'http://169.254.169.254/latest/meta-data',
            'http://[::1]/mcp',
            'http://metadata.google.internal/',
            // One public plus one private record must still fail.
            'https://rebind.example.com/mcp',
            'file:///etc/passwd',
        ] as $url) {
            $this->assertNotNull($policy->violation($url), "Expected {$url} to be blocked");
        }
    }

    public function test_operator_allowlist_permits_private_hosts_and_cidrs_but_never_metadata(): void
    {
        config(['mcp.allowed_private_hosts' => ['localhost', 'mcp.internal', '10.20.0.0/16']]);
        $this->fakeDns([
            'localhost' => ['127.0.0.1'],
            'mcp.internal' => ['192.168.50.4'],
            'tools.lan' => ['10.20.3.4'],
        ]);
        $policy = app(McpUrlPolicy::class);

        $this->assertNull($policy->violation('http://localhost:3000/mcp'));
        $this->assertNull($policy->violation('http://mcp.internal/mcp'));
        $this->assertNull($policy->violation('http://tools.lan/mcp'));
        $this->assertNull($policy->violation('http://10.20.9.9/mcp'));

        // Outside the CIDR, and metadata ranges, stay blocked.
        $this->assertNotNull($policy->violation('http://10.21.0.1/mcp'));
        $this->assertNotNull($policy->violation('http://169.254.169.254/'));

        config(['mcp.allowed_private_hosts' => ['169.254.0.0/16', 'metadata.google.internal']]);
        $this->assertNotNull($policy->violation('http://169.254.169.254/'));
        $this->assertNotNull($policy->violation('http://metadata.google.internal/'));
    }

    public function test_controller_rejects_private_mcp_url_on_create_and_update(): void
    {
        $user = User::factory()->create();
        Http::fake();

        $this->actingAs($user)->postJson('/api/mcp-servers', [
            'name' => 'Internal',
            'url' => 'http://127.0.0.1:9000/mcp',
        ])->assertStatus(422)->assertJsonValidationErrors('url');

        $server = McpServer::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Public',
            'slug' => 'public',
            'url' => 'https://93.184.216.34/mcp',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
        ]);

        $this->actingAs($user)->patchJson("/api/mcp-servers/{$server->id}", [
            'url' => 'http://169.254.169.254/latest',
        ])->assertStatus(422)->assertJsonValidationErrors('url');

        $this->assertSame('https://93.184.216.34/mcp', $server->fresh()->url);
        Http::assertNothingSent();
    }

    public function test_client_revalidates_stored_urls_before_every_request(): void
    {
        // A row saved before the guard existed (or edited directly in the DB)
        // must still be refused at request time, with no HTTP leaving.
        Http::fake();
        $server = new McpServer(['url' => 'http://10.0.0.8/mcp', 'auth_type' => 'none', 'timeout' => 5]);

        try {
            McpClient::fromServer($server)->initialize();
            $this->fail('Expected the MCP URL policy to refuse a private address.');
        } catch (McpUrlNotAllowed $e) {
            $this->assertStringContainsString('MCP_ALLOWED_PRIVATE_HOSTS', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_client_does_not_follow_redirects(): void
    {
        Http::fake([
            'https://93.184.216.34/*' => Http::response('', 307, ['Location' => 'http://169.254.169.254/']),
            '169.254.169.254/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['leaked' => true]]),
        ]);

        $server = new McpServer(['url' => 'https://93.184.216.34/mcp', 'auth_type' => 'none', 'timeout' => 5]);

        try {
            McpClient::fromServer($server)->initialize();
            $this->fail('Expected redirect to be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('redirect', $e->getMessage());
        }

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
    }

    // Oversized MCP responses are rejected before parsing (config/mcp.php).
    public function test_client_rejects_oversized_responses(): void
    {
        config(['mcp.max_response_bytes' => 1024]);
        Http::fake(['https://93.184.216.34/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['blob' => str_repeat('x', 4096)]])]);
        $server = new McpServer(['url' => 'https://93.184.216.34/mcp', 'auth_type' => 'none', 'timeout' => 5]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds the 1024-byte limit');
        McpClient::fromServer($server)->initialize();
    }

    public function test_agent_add_mcp_server_tool_refuses_private_url_without_saving(): void
    {
        Http::fake();
        $agent = User::factory()->agent()->create();

        $result = (new AddMcpServer($agent))->handle(new Request([
            'name' => 'Sneaky',
            'url' => 'http://192.168.0.1/mcp',
        ]));

        $this->assertStringContainsString('not allowed', $result);
        $this->assertSame(0, McpServer::count());
        Http::assertNothingSent();
    }
}
