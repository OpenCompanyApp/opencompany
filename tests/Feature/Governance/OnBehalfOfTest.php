<?php

namespace Tests\Feature\Governance;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\Authorization\Application\OnBehalfOf;
use App\Models\AgentRunRecord;
use App\Models\ApprovalRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Security invariant (pillar 5, decision step 9): an agent acting for a human
 * never exceeds that human's access. The principal is resolved from the task
 * chain (requester, routine owner, delegation), persisted on the run, applied
 * to every decision inside the run, and stamped on approvals so replay
 * re-checks it.
 */
class OnBehalfOfTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace->update(['governance_preset' => 'personal']);
        $this->agent = User::factory()->agent()->create(['status' => 'idle']);
        $this->member = User::factory()->create(['name' => 'Morgan']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $this->member->id, 'role' => 'member']);
    }

    private function task(?string $requesterId, ?string $parentId = null, string $source = Task::SOURCE_CHAT): Task
    {
        return Task::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 't',
            'type' => Task::TYPE_CUSTOM, 'status' => Task::STATUS_ACTIVE, 'priority' => Task::PRIORITY_NORMAL,
            'source' => $source, 'agent_id' => $this->agent->id, 'requester_id' => $requesterId,
            'parent_task_id' => $parentId]);
    }

    /** @return array{allowed: bool, requires_approval: bool} */
    private function inRun(AgentRunRecord $run, string $slug): array
    {
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));
        try {
            return app(AgentPermissionService::class)->resolveToolPermission($this->agent, $slug, 'write');
        } finally {
            app()->forgetInstance(ActiveAgentRun::class);
        }
    }

    public function test_principal_is_the_first_human_up_the_task_chain_and_is_persisted_on_the_run(): void
    {
        $root = $this->task($this->member->id);
        $delegated = $this->task($this->agent->id, $root->id, Task::SOURCE_AGENT_DELEGATION);
        $orphan = $this->task($this->agent->id);

        $this->assertSame($this->member->id, app(OnBehalfOf::class)->principalForTask($delegated));
        $this->assertNull(app(OnBehalfOf::class)->principalForTask($orphan));
        $this->assertSame($this->member->id, AgentRunRecord::ensureForTask($delegated, AgentRunRecord::KIND_ASSIGNED_TASK)->on_behalf_of_user_id);
    }

    // A plain member cannot drive governance or exposure actions through an agent,
    // even when the preset or the agent's grants would allow them.
    public function test_non_admin_principal_cannot_drive_governance_or_exposure_actions(): void
    {
        $run = AgentRunRecord::ensureForTask($this->task($this->member->id), AgentRunRecord::KIND_CHAT);

        $this->assertFalse($this->inRun($run, 'update_agent_tool_permissions')['allowed']);
        $this->assertFalse($this->inRun($run, 'add_mcp_server')['allowed']);
        $this->assertTrue($this->inRun($run, 'update_document')['allowed']);

        WorkspaceMember::query()->where('user_id', $this->member->id)->update(['role' => 'admin']);
        $this->assertTrue($this->inRun($run, 'update_agent_tool_permissions')['allowed']);
    }

    // A requester removed from the workspace mid-run takes the agent's access with them.
    public function test_removed_principal_denies_everything_at_execution_time(): void
    {
        $run = AgentRunRecord::ensureForTask($this->task($this->member->id), AgentRunRecord::KIND_CHAT);
        WorkspaceMember::query()->where('user_id', $this->member->id)->delete();

        $this->assertFalse($this->inRun($run, 'update_document')['allowed']);
    }

    // Approvals carry the principal so replay outside the run re-checks it.
    public function test_approvals_are_stamped_and_replay_rechecks_the_principal(): void
    {
        $run = AgentRunRecord::ensureForTask($this->task($this->member->id), AgentRunRecord::KIND_CHAT);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));
        $approval = ApprovalRequest::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id,
            'type' => 'action', 'title' => 't', 'description' => 'd', 'requester_id' => $this->agent->id,
            'status' => 'pending', 'tool_execution_context' => ['tool_slug' => 'update_document', 'parameters' => []]]);
        app()->forgetInstance(ActiveAgentRun::class);

        $this->assertSame($this->member->id, $approval->tool_execution_context['on_behalf_of_user_id']);

        WorkspaceMember::query()->where('user_id', $this->member->id)->delete();
        $decision = app(OnBehalfOf::class)->runAs(
            $approval->tool_execution_context['on_behalf_of_user_id'],
            fn () => app(AgentPermissionService::class)->resolveToolPermission($this->agent, 'update_document', 'write'),
        );
        $this->assertFalse($decision['allowed']);
        $this->assertTrue(app(AgentPermissionService::class)->resolveToolPermission($this->agent, 'update_document', 'write')['allowed'], 'override is scoped');
    }
}
