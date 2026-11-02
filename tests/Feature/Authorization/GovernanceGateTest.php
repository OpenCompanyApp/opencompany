<?php

namespace Tests\Feature\Authorization;

use App\Agents\Runtime\ActiveAgentRun;
use App\Domain\Authorization\Policy\ActionClassifier;
use App\Models\AgentPermission;
use App\Models\AgentRunRecord;
use App\Models\PolicyGrant;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Owner-locked governance revision (Product Direction pillar 5,
 * docs/architecture/governance-model.md): reversibility, not "write", decides
 * the gate; presets choose defaults only and never weaken the invariants
 * (floor, explicit deny, trigger-started external actions, no "always"
 * exception for purge/exposure/structural, unclassified = external).
 */
class GovernanceGateTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->agent()->create(['status' => 'idle']);
    }

    /** @return array{allowed: bool, requires_approval: bool} */
    private function resolve(string $slug, string $type = 'write'): array
    {
        return app(AgentPermissionService::class)->resolveToolPermission($this->agent, $slug, $type);
    }

    private function preset(string $preset): void
    {
        $this->workspace->update(['governance_preset' => $preset]);
    }

    private function allowRow(string $slug): void
    {
        AgentPermission::create(['id' => (string) Str::uuid(), 'agent_id' => $this->agent->id, 'scope_type' => 'tool',
            'scope_key' => $slug, 'permission' => 'allow', 'requires_approval' => false]);
    }

    private function grant(string $slug, string $scope): void
    {
        PolicyGrant::create(['workspace_id' => $this->workspace->id, 'agent_id' => $this->agent->id,
            'tool_slug' => $slug, 'scope' => $scope]);
    }

    // Preset resolution: explicit value, else Personal for one member, Team otherwise.
    public function test_effective_preset_defaults_by_membership(): void
    {
        WorkspaceMember::query()->where('workspace_id', $this->workspace->id)->delete();
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => User::factory()->create()->id, 'role' => 'admin']);
        $this->assertSame('personal', $this->workspace->fresh()->effectiveGovernancePreset());

        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => User::factory()->create()->id, 'role' => 'member']);
        $this->assertSame('team', $this->workspace->fresh()->effectiveGovernancePreset());

        $this->preset('company');
        $this->assertSame('company', $this->workspace->fresh()->effectiveGovernancePreset());
        $this->preset('bogus');
        $this->assertSame('team', $this->workspace->fresh()->effectiveGovernancePreset(), 'unknown values fall back');
    }

    // Reversible internal edits auto-apply in every preset; external ones ask in every preset.
    public function test_reversible_edits_auto_apply_and_external_actions_ask_in_every_preset(): void
    {
        foreach (['personal', 'team', 'company'] as $preset) {
            $this->preset($preset);
            foreach (['update_document', 'vfs_write', 'add_table_row', 'create_list_item'] as $slug) {
                $this->assertFalse($this->resolve($slug)['requires_approval'], "{$slug} under {$preset}");
            }
            foreach (['mcp_github__create_issue', 'leave_external_channel', 'add_mcp_server', 'some_unknown_tool', 'create_table', 'update_agent_identity_file'] as $slug) {
                $this->assertTrue($this->resolve($slug)['requires_approval'], "{$slug} under {$preset}");
            }
        }
    }

    // Delete is trash: auto in Personal/Team, a proposal in Company. Purge asks in Team.
    public function test_delete_and_purge_follow_the_preset_table(): void
    {
        $this->preset('team');
        $this->assertFalse($this->resolve('delete_document')['requires_approval']);
        $this->assertTrue($this->resolve('delete_message')['requires_approval'], 'hard delete is a purge');

        $this->preset('personal');
        $this->assertFalse($this->resolve('delete_message')['requires_approval']);

        $this->preset('company');
        $this->assertTrue($this->resolve('delete_document')['requires_approval']);
    }

    // The floor is denied before exemptions, grants, admin rules or presets.
    public function test_floor_is_denied_even_with_grants_and_admin_rules(): void
    {
        config(['governance.floor_tools' => ['export_credentials']]);
        $this->preset('personal');
        $this->grant('export_credentials', 'always');
        $this->allowRow('export_credentials');

        $this->assertSame(ActionClassifier::FLOOR, app(ActionClassifier::class)->classify('export_credentials', ['type' => 'read']));
        $this->assertFalse($this->resolve('export_credentials', 'read')['allowed']);
    }

    // "Always" is never honoured for exposure_widening, structural or purge; narrower scopes are.
    public function test_always_exceptions_do_not_cover_exposure_structural_or_purge(): void
    {
        $this->preset('team');
        foreach (['add_mcp_server', 'create_table', 'delete_message'] as $slug) {
            $this->grant($slug, 'always');
            $this->allowRow($slug);
            $this->assertTrue($this->resolve($slug)['requires_approval'], "{$slug} ignores always");
        }

        $this->grant('add_mcp_server', 'once');
        $this->assertFalse($this->resolve('add_mcp_server')['requires_approval'], 'a once grant still covers one instance');

        // Ordinary external sends accept an always exception for the exact tool.
        $this->grant('mcp_github__create_issue', 'always');
        $this->assertFalse($this->resolve('mcp_github__create_issue')['requires_approval']);
    }

    // A trigger is never consent: routine-started runs ask for external actions despite exceptions.
    public function test_trigger_started_runs_always_ask_for_external_actions(): void
    {
        $this->preset('personal');
        $this->grant('mcp_github__create_issue', 'always');

        $task = Task::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 'r',
            'type' => Task::TYPE_CUSTOM, 'status' => Task::STATUS_ACTIVE, 'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_ROUTINE, 'agent_id' => $this->agent->id, 'requester_id' => $this->agent->id]);
        $run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_ROUTINE_AGENT);

        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));
        try {
            $this->assertTrue($this->resolve('mcp_github__create_issue')['requires_approval']);
            $this->assertFalse($this->resolve('update_document')['requires_approval'], 'reversible edits still auto-apply');
        } finally {
            app()->forgetInstance(ActiveAgentRun::class);
        }

        $this->assertFalse($this->resolve('mcp_github__create_issue')['requires_approval'], 'human-started: exception applies');
    }

    // Remote MCP servers cannot declare themselves into a cheaper class.
    public function test_mcp_tools_cannot_declare_their_own_class(): void
    {
        $classifier = app(ActionClassifier::class);
        $this->assertSame(ActionClassifier::EXTERNAL_SEND, $classifier->classify('mcp_docs__search', ['type' => 'read', 'action_class' => 'read']));
        $this->assertSame(ActionClassifier::EXTERNAL_SEND, $classifier->classify('never_heard_of_it', ['type' => 'write']));
    }

    // An admin rule can make a reversible tool stricter than the preset.
    public function test_admin_rule_can_tighten_a_reversible_tool(): void
    {
        AgentPermission::create(['id' => (string) Str::uuid(), 'agent_id' => $this->agent->id, 'scope_type' => 'tool',
            'scope_key' => 'update_document', 'permission' => 'allow', 'requires_approval' => true]);
        $this->assertTrue($this->resolve('update_document')['requires_approval']);
    }

    // Only admins set the preset; changes are audited in the event log.
    public function test_admins_set_the_preset_through_the_api_and_it_is_audited(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member']);
        $headers = ['X-Workspace-Id' => $this->workspace->id];

        $this->be($member)->withHeaders($headers)->patchJson('/api/workspace', ['governancePreset' => 'personal'])->assertForbidden();
        $this->actingAs($admin)->withHeaders($headers)->patchJson('/api/workspace', ['governancePreset' => 'bogus'])->assertUnprocessable();
        $this->actingAs($admin)->withHeaders($headers)->patchJson('/api/workspace', ['governancePreset' => 'company'])->assertOk();

        $this->assertSame('company', $this->workspace->fresh()->governance_preset);
        $this->assertTrue(\App\Models\WorkspaceEvent::query()->where('type', 'workspace.governance_preset_changed')->exists());
    }
}
