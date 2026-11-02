<?php

namespace Tests\Feature\Authorization;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\System\ConsumesOnceGrantTool;
use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\AgentRunRecord;
use App\Models\PolicyGrant;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Policy model (Product Direction pillar 5): behaviour modes are gone. Only
 * declared reads run without approval; admins grant scoped, exact-match
 * exceptions (once, run, task, until, always). A deny always wins.
 */
class PolicyModelTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private AgentPermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->agent()->create(['status' => 'idle']);
        $this->permissions = app(AgentPermissionService::class);
    }

    private function requiresApproval(string $slug, string $type): bool
    {
        return $this->permissions->resolveToolPermission($this->agent, $slug, $type)['requires_approval'];
    }

    private function grant(string $slug, string $scope, array $extra = []): PolicyGrant
    {
        return PolicyGrant::create(['workspace_id' => $this->workspace->id, 'agent_id' => $this->agent->id,
            'tool_slug' => $slug, 'scope' => $scope, ...$extra]);
    }

    public function test_default_policy_runs_only_declared_reads(): void
    {
        $this->assertFalse($this->requiresApproval('list_docs', 'read'));
        foreach (['write', 'action', '', 'custom'] as $type) {
            $this->assertTrue($this->requiresApproval('some_tool', $type), "type '{$type}'");
        }
        $this->assertTrue($this->requiresApproval('mcp_github__list_issues', 'read'), 'MCP is external even when typed read');
    }

    public function test_always_and_until_grants_match_exactly_and_expire(): void
    {
        $this->grant('update_doc', 'always');
        $this->assertFalse($this->requiresApproval('update_doc', 'write'));
        $this->assertTrue($this->requiresApproval('update_docs', 'write'), 'exact match only');

        $this->grant('create_table', 'until', ['expires_at' => now()->subMinute()]);
        $this->assertTrue($this->requiresApproval('create_table', 'write'), 'expired grant');
        $this->grant('create_table', 'until', ['expires_at' => now()->addHour()]);
        $this->assertFalse($this->requiresApproval('create_table', 'write'));
    }

    public function test_run_and_task_grants_only_apply_inside_that_run(): void
    {
        $task = Task::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 't',
            'type' => Task::TYPE_CUSTOM, 'status' => Task::STATUS_ACTIVE, 'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT, 'agent_id' => $this->agent->id, 'requester_id' => $this->agent->id]);
        $run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_CHAT);
        $this->grant('add_table_row', 'run', ['run_id' => $run->id]);
        $this->grant('update_doc', 'task', ['task_id' => $task->id]);

        $this->assertTrue($this->requiresApproval('add_table_row', 'write'), 'outside the run');
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));
        $this->assertFalse($this->requiresApproval('add_table_row', 'write'));
        $this->assertFalse($this->requiresApproval('update_doc', 'write'));
        app()->forgetInstance(ActiveAgentRun::class);
    }

    public function test_deny_beats_any_grant_and_revoked_grants_do_not_match(): void
    {
        AgentPermission::create(['id' => (string) Str::uuid(), 'agent_id' => $this->agent->id, 'scope_type' => 'tool',
            'scope_key' => 'delete_doc', 'permission' => 'deny', 'requires_approval' => false]);
        $this->grant('delete_doc', 'always');
        $this->assertFalse($this->permissions->resolveToolPermission($this->agent, 'delete_doc', 'write')['allowed']);

        $this->grant('update_doc', 'always', ['revoked_at' => now()]);
        $this->assertTrue($this->requiresApproval('update_doc', 'write'));
    }

    // A single-use grant is consumed by execution, never by registration.
    public function test_once_grant_is_consumed_on_execution_only(): void
    {
        $grant = $this->grant('vfs_write', 'once');
        $registry = app(ToolRegistry::class);

        $tools = $registry->getToolsForAgent($this->agent);
        $wrapped = collect($tools)->first(fn ($tool) => $tool instanceof ConsumesOnceGrantTool);
        $this->assertNotNull($wrapped, 'covered tool is registered with the consuming decorator');
        $this->assertNull($grant->fresh()->consumed_at, 'registration does not consume');

        app(\App\Domain\Authorization\Application\PolicyGrantService::class)->consumeOnce($this->agent, 'vfs_write');
        $this->assertNotNull($grant->fresh()->consumed_at);
        $this->assertTrue($this->requiresApproval('vfs_write', 'write'));
    }

    public function test_contacting_a_peer_outside_the_manager_chain_needs_approval_by_default(): void
    {
        $peer = User::factory()->agent()->create();
        $this->assertTrue($this->permissions->canContactAgent($this->agent, $peer)['requires_approval']);
    }

    public function test_only_workspace_admins_manage_grants_for_their_own_agents(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $admin->id, 'role' => 'admin']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member']);
        $foreign = User::factory()->agent()->create(['workspace_id' => Workspace::create(['name' => 'B', 'slug' => 'b-policy'])->id]);
        $url = fn (string $id) => "/api/agents/{$id}/policy-grants";
        $headers = ['X-Workspace-Id' => $this->workspace->id];

        $this->actingAs($member)->withHeaders($headers)->postJson($url($this->agent->id), ['toolSlug' => 'update_doc', 'scope' => 'always'])->assertForbidden();
        $this->actingAs($admin)->withHeaders($headers)->postJson($url($this->agent->id), ['toolSlug' => 'update_doc', 'scope' => 'always'])->assertCreated();
        $this->actingAs($admin)->withHeaders($headers)->postJson($url($foreign->id), ['toolSlug' => 'update_doc', 'scope' => 'always'])->assertNotFound();
        $this->assertFalse($this->requiresApproval('update_doc', 'write'));
    }
}
