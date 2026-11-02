<?php

namespace Tests\Feature\AgentSafety;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Authorization\Application\AgentPermissionResolver;
use App\Models\AgentPermission;
use App\Models\IntegrationSetting;
use App\Models\McpServer;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\Mcp\McpPermissionEvaluator;
use App\Services\Mcp\McpToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Agents\Tools\External\Tool;
use App\Agents\Tools\External\ToolProvider;
use App\Agents\Tools\External\ToolProviderRegistry;
use App\Agents\Tools\External\ToolResult;
use Tests\TestCase;

/**
 * Approval floor for outbound/external mutations ("triggers are never
 * consent"): with no admin rule an agent gets approval_required for tools that
 * change third-party systems or widen exposure, on both the direct and the
 * Code Mode callback path. Under the policy model (behaviour modes deleted)
 * internal writes are gated by default too; only declared reads run freely,
 * and an explicit admin allow row can opt a tool out.
 */
class ExternalApprovalFloorTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create();

        // A credentialed third-party integration (acts on an external account)
        // and a credential-less one (local renderer that only writes files).
        app(ToolProviderRegistry::class)->register($this->fakeProvider('acmecrm', credentialed: true));
        app(ToolProviderRegistry::class)->register($this->fakeProvider('acmerender', credentialed: false));

        foreach (['acmecrm', 'acmerender'] as $integration) {
            IntegrationSetting::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $this->workspace->id,
                'integration_id' => $integration,
                'config' => [],
                'enabled' => true,
            ]);
        }
    }

    private function fakeProvider(string $app, bool $credentialed): ToolProvider
    {
        return new class($app, $credentialed) implements ToolProvider
        {
            public function __construct(private string $app, private bool $credentialed) {}

            public function appName(): string
            {
                return $this->app;
            }

            public function appMeta(): array
            {
                return ['label' => $this->app, 'description' => 'Fake', 'icon' => 'ph:plug'];
            }

            public function tools(): array
            {
                return [
                    "{$this->app}_get_record" => ['class' => FakeIntegrationTool::class, 'type' => 'read', 'name' => 'Get', 'description' => 'Read', 'icon' => 'ph:eye'],
                    "{$this->app}_update_record" => ['class' => FakeIntegrationTool::class, 'type' => 'write', 'name' => 'Update', 'description' => 'Write', 'icon' => 'ph:pencil'],
                    "{$this->app}_untyped" => ['class' => FakeIntegrationTool::class, 'name' => 'Untyped', 'description' => 'No type', 'icon' => 'ph:question'],
                ];
            }

            public function isIntegration(): bool
            {
                return true;
            }

            public function createTool(string $class, array $context = []): Tool
            {
                return new FakeIntegrationTool;
            }

            public function scriptDocsPath(): ?string
            {
                return null;
            }

            public function credentialFields(): array
            {
                return $this->credentialed
                    ? [['key' => 'api_key', 'type' => 'secret', 'label' => 'API key', 'required' => true]]
                    : [];
            }
        };
    }

    /** @return array{allowed: bool, requires_approval: bool} */
    private function resolve(string $slug, string $type = 'write'): array
    {
        return app(AgentPermissionService::class)->resolveToolPermission($this->agent, $slug, $type);
    }

    public function test_agent_needs_approval_for_external_write_integration_tool(): void
    {
        $this->assertSame(['allowed' => true, 'requires_approval' => true], $this->resolve('acmecrm_update_record'));
        // Unknown/missing type on an external integration fails closed.
        $this->assertTrue($this->resolve('acmecrm_untyped')['requires_approval']);
        // Declared reads of the same integration run without approval. The
        // resolver now trusts the declared type it is given (the integration
        // metadata re-lookup went away with the package system); the Code Mode
        // test below covers the path that resolves the type from metadata.
        $this->assertFalse($this->resolve('acmecrm_get_record', 'read')['requires_approval']);
    }

    public function test_code_mode_callback_gets_the_same_approval_decision(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertSame('approval_required', $registry->resolveScriptToolForDispatch('acmecrm_update_record', $this->agent)['decision']);
        $this->assertSame('allow', $registry->resolveScriptToolForDispatch('acmecrm_get_record', $this->agent)['decision']);
    }

    public function test_explicit_admin_allow_row_opts_a_tool_out_of_the_floor(): void
    {
        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'acmecrm_update_record',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);

        $this->assertSame(['allowed' => true, 'requires_approval' => false], $this->resolve('acmecrm_update_record'));
        $this->assertSame('allow', app(ToolRegistry::class)->resolveScriptToolForDispatch('acmecrm_update_record', $this->agent)['decision']);
    }

    // Governance revision: reversible internal edits auto-apply; unclassified
    // tools are external and ask; an admin allow row waives exactly one tool.
    public function test_reversible_edits_auto_apply_and_unclassified_tools_ask(): void
    {
        foreach (['create_document', 'vfs_write'] as $slug) {
            $this->assertFalse($this->resolve($slug)['requires_approval'], "{$slug} is a reversible edit");
        }
        foreach (['create_task', 'acmerender_update_record'] as $slug) {
            $this->assertTrue($this->resolve($slug)['requires_approval'], "{$slug} is unclassified, so external");
        }

        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'acmerender_update_record',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        $this->assertFalse($this->resolve('acmerender_update_record')['requires_approval']);
        $this->assertTrue($this->resolve('create_task')['requires_approval']);
    }

    public function test_mcp_tools_require_approval_by_default_on_every_path(): void
    {
        $server = McpServer::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Docs MCP',
            'slug' => 'docs_mcp',
            'url' => 'https://93.184.216.34/mcp',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout' => 30,
            // A remote server claiming read-only is not trusted for the floor.
            'discovered_tools' => [['name' => 'search-docs', 'annotations' => ['readOnlyHint' => true], 'inputSchema' => ['type' => 'object']]],
        ]);
        app(ToolProviderRegistry::class)->register(new McpToolProvider($server));

        $this->assertTrue($this->resolve('mcp_docs_mcp__search_docs', 'read')['requires_approval']);
        $this->assertSame('approval_required', app(McpPermissionEvaluator::class)->evaluate($this->agent, $server, 'search-docs')->decision);
        $this->assertSame('approval_required', app(ToolRegistry::class)->resolveScriptToolForDispatch('mcp_docs_mcp__search_docs', $this->agent)['decision']);
    }

    public function test_exposure_widening_builtins_require_approval_and_send_hook_honours_opt_out(): void
    {
        foreach (['add_mcp_server', 'update_mcp_server', 'remove_mcp_server', 'setup_integration_webhook', 'link_external_user'] as $slug) {
            $this->assertTrue($this->resolve($slug)['requires_approval'], "{$slug} should require approval");
        }

        // contact_agent exemption is intentionally unchanged.
        $this->assertFalse($this->resolve('contact_agent')['requires_approval']);

        $resolver = app(AgentPermissionResolver::class);
        $this->assertTrue($resolver->requiresExternalChannelSendApproval($this->agent));

        AgentPermission::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'send_channel_message',
            'permission' => 'allow',
            'requires_approval' => false,
        ]);
        $this->assertFalse($resolver->requiresExternalChannelSendApproval($this->agent));
    }
}

/**
 * Minimal integration tool used only to satisfy provider construction. No
 * test executes it; nothing leaves the process.
 */
class FakeIntegrationTool implements Tool
{
    public function name(): string
    {
        return 'fake';
    }

    public function description(): string
    {
        return 'Fake tool';
    }

    public function parameters(): array
    {
        return [];
    }

    public function execute(array $args): ToolResult
    {
        return ToolResult::success(['ok' => true]);
    }
}
