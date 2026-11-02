<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ApprovalExecutionService;
use App\Services\Integrations\IntegrationRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use OpenCompany\IntegrationCore\Contracts\Tool as IntegrationTool;
use OpenCompany\IntegrationCore\Script\ScriptDispatchException;
use OpenCompany\IntegrationCore\Support\ToolResult;
use Tests\TestCase;

/**
 * Security regressions for Code Mode's callback authorization boundary.
 *
 * These cases use registered metadata and fake tool implementations only. No
 * test permits an integration provider request to leave the process.
 */
class ScriptCallbackAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create();
        $this->registry = app(ToolRegistry::class);
        app()->instance('currentWorkspace', Workspace::findOrFail($this->agent->workspace_id));
    }

    public function test_revoked_tool_is_denied_at_callback_time_after_it_was_discovered(): void
    {
        $catalog = $this->registry->getScriptToolCatalog($this->agent);
        $this->assertTrue(collect($catalog)->contains(
            static fn (array $app): bool => collect($app['tools'])->contains('slug', 'web_search'),
        ));

        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'tool',
            'scope_key' => 'web_search',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        // Discovery happened before the policy mutation. Dispatch must still
        // stop before construction, so no provider request can be attempted.
        $dispatch = $this->registry->resolveScriptToolForDispatch('web_search', $this->agent);

        $this->assertSame('deny', $dispatch['decision']);
        $this->assertArrayNotHasKey('tool', $dispatch);
    }

    public function test_disabled_integration_is_denied_before_its_tool_is_constructed(): void
    {
        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $this->agent->id,
            'scope_type' => 'integration',
            'scope_key' => 'coingecko',
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        $dispatch = $this->registry->resolveScriptToolForDispatch('coingecko_search_coins', $this->agent);

        $this->assertSame('deny', $dispatch['decision']);
        $this->assertArrayNotHasKey('tool', $dispatch);
        $this->assertStringContainsString('not enabled', $dispatch['reason']);
    }

    public function test_approval_required_callback_preserves_exact_context_and_stops_before_dispatch(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolveScriptToolForDispatch')
            ->once()
            ->with('fake_write', $this->agent, 'personal')
            ->andReturn(['decision' => 'approval_required', 'reason' => 'Approval required']);
        $registry->shouldReceive('getChannelContext')->once()->andReturnNull();

        try {
            (new IntegrationRuntime($registry))->call($this->agent, 'fake_write', [
                'record_id' => 'record-1',
                'enabled' => false,
            ], 'personal');
            $this->fail('Expected a pending approval disposition.');
        } catch (ScriptDispatchException $exception) {
            $this->assertSame('approval_pending', $exception->errorType);
            $this->assertArrayHasKey('approval_id', $exception->details);
        }

        $approval = ApprovalRequest::sole();
        $this->assertSame('pending', $approval->status);
        $this->assertSame([
            'kind' => 'script_callback',
            'tool_slug' => 'fake_write',
            'parameters' => ['record_id' => 'record-1', 'enabled' => false],
            'account' => 'personal',
            'workspace_id' => $this->agent->workspace_id,
        ], $approval->tool_execution_context);
    }

    public function test_approved_script_callback_executes_the_exact_integration_tool_once_not_the_ruby_program(): void
    {
        $tool = new class implements IntegrationTool
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function name(): string
            {
                return 'fake_write';
            }

            public function description(): string
            {
                return 'A fake approved callback.';
            }

            public function parameters(): array
            {
                return [];
            }

            public function execute(array $args): ToolResult
            {
                $this->calls[] = $args;

                return ToolResult::success(['accepted' => true]);
            }
        };

        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Script callback: fake_write',
            'description' => 'Test callback',
            'requester_id' => $this->agent->id,
            'status' => 'approved',
            'tool_execution_context' => [
                'kind' => 'script_callback',
                'tool_slug' => 'fake_write',
                'parameters' => ['record_id' => 'record-1', 'enabled' => false],
                'account' => 'personal',
                'workspace_id' => $this->agent->workspace_id,
            ],
        ]);

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolveScriptToolForDispatch')
            ->once()
            ->with('fake_write', Mockery::type(User::class), 'personal')
            ->andReturn(['decision' => 'approval_required', 'reason' => 'Existing approval is required']);
        $registry->shouldReceive('instantiateToolBySlug')
            ->once()
            ->with('fake_write', Mockery::type(User::class), 'personal')
            ->andReturn($tool);

        $service = new ApprovalExecutionService($registry);
        $service->executeApprovedTool($approval, false);
        $service->executeApprovedTool($approval, false);

        $this->assertSame([['record_id' => 'record-1', 'enabled' => false]], $tool->calls);
        $approval->refresh();
        $this->assertArrayHasKey('script_callback_dispatched_at', $approval->tool_execution_context);
        $this->assertSame('succeeded', $approval->tool_execution_context['script_callback_disposition']);
        $this->assertSame('succeeded', $approval->tool_execution_context['script_callback_receipt']['disposition']);
    }

    public function test_approved_callback_rechecks_a_later_permission_revoke_without_dispatching(): void
    {
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Script callback: fake_write',
            'description' => 'Test callback',
            'requester_id' => $this->agent->id,
            'status' => 'approved',
            'tool_execution_context' => [
                'kind' => 'script_callback',
                'tool_slug' => 'fake_write',
                'parameters' => ['record_id' => 'record-1'],
                'account' => 'personal',
                'workspace_id' => $this->agent->workspace_id,
            ],
        ]);

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolveScriptToolForDispatch')
            ->once()
            ->with('fake_write', Mockery::type(User::class), 'personal')
            ->andReturn(['decision' => 'deny', 'reason' => 'Permission was revoked']);
        $registry->shouldNotReceive('instantiateToolBySlug');

        (new ApprovalExecutionService($registry))->executeApprovedTool($approval, false);

        $approval->refresh();
        $this->assertSame('denied', $approval->tool_execution_context['script_callback_disposition']);
        $this->assertSame('denied', $approval->tool_execution_context['script_callback_receipt']['disposition']);
        $this->assertArrayNotHasKey('script_callback_dispatched_at', $approval->tool_execution_context);
    }

    public function test_approval_execution_uses_locked_database_context_not_a_stale_model_payload(): void
    {
        $approval = ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'action',
            'title' => 'Script callback: original_write',
            'description' => 'Test callback',
            'requester_id' => $this->agent->id,
            'status' => 'approved',
            'tool_execution_context' => [
                'kind' => 'script_callback',
                'tool_slug' => 'original_write',
                'parameters' => ['record_id' => 'old'],
                'account' => 'old-account',
                'workspace_id' => $this->agent->workspace_id,
            ],
        ]);

        // Simulate a stale approval object held by a webhook while the durable
        // request was updated before it reached the row lock.
        ApprovalRequest::whereKey($approval->id)->update([
            'tool_execution_context' => [
                'kind' => 'script_callback',
                'tool_slug' => 'locked_write',
                'parameters' => ['record_id' => 'locked'],
                'account' => 'locked-account',
                'workspace_id' => $this->agent->workspace_id,
            ],
        ]);

        $tool = new class implements IntegrationTool
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function name(): string
            {
                return 'locked_write';
            }

            public function description(): string
            {
                return 'Fake locked callback.';
            }

            public function parameters(): array
            {
                return [];
            }

            public function execute(array $args): ToolResult
            {
                $this->calls[] = $args;

                return ToolResult::success(['accepted' => true]);
            }
        };

        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolveScriptToolForDispatch')
            ->once()
            ->with('locked_write', Mockery::type(User::class), 'locked-account')
            ->andReturn(['decision' => 'allow', 'reason' => 'Allowed', 'tool' => $tool]);
        $registry->shouldNotReceive('instantiateToolBySlug');

        (new ApprovalExecutionService($registry))->executeApprovedTool($approval, false);

        $this->assertSame([['record_id' => 'locked']], $tool->calls);
    }
}
