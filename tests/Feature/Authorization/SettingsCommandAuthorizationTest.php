<?php

namespace Tests\Feature\Authorization;

use App\Models\AuthorizationLedgerEntry;
use App\Models\ConversationSummary;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Settings danger actions are separate, separately authorized and audited
 * commands (ROADMAP P0 item 8; GIN-1149). Operational commands need an
 * admin; data-erasing commands need the workspace owner; members and admins
 * of another workspace get nothing; refused commands have no side effects;
 * the shared application log is shown only to a platform operator.
 */
class SettingsCommandAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->human('admin');
        $this->workspace->update(['owner_id' => $this->owner->id]);
        $this->admin = $this->human('admin');
        $this->member = $this->human('member');
    }

    public function test_operational_commands_need_an_admin_and_are_audited(): void
    {
        $this->actingAs($this->member)->postJson('/api/settings/commands/pause_agents')->assertForbidden();

        $this->actingAs($this->admin)->postJson('/api/settings/commands/pause_agents')->assertOk();
        $this->actingAs($this->admin)->postJson('/api/settings/danger-action', ['action' => 'resume_agents'])->assertOk();

        $changes = AuthorizationLedgerEntry::query()->where('kind', AuthorizationLedgerEntry::KIND_SECURITY_CHANGE)
            ->orderBy('sequence')->pluck('action')->all();
        $this->assertSame(['settings.command.pause_agents', 'settings.command.resume_agents'], $changes);
    }

    public function test_data_erasing_commands_are_owner_only_and_refusals_have_no_side_effects(): void
    {
        $summary = $this->summary();

        $this->actingAs($this->admin)->postJson('/api/settings/danger-action', ['action' => 'clear_conversation_summaries'])
            ->assertForbidden()->assertJsonPath('error', 'capability_required');
        $this->assertNotNull($summary->fresh());
        $this->assertTrue(AuthorizationLedgerEntry::query()
            ->where('action', 'settings.command.clear_conversation_summaries')->where('effect', 'deny')
            ->where('actor_id', $this->admin->id)->exists());

        $this->actingAs($this->owner)->postJson('/api/settings/commands/clear_conversation_summaries')->assertOk();
        $this->assertNull($summary->fresh());
    }

    public function test_admin_of_another_workspace_cannot_run_commands_here(): void
    {
        $other = Workspace::create(['id' => (string) Str::uuid(), 'name' => 'B', 'slug' => 'b-'.Str::random(5)]);
        $foreignAdmin = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $foreignAdmin->id, 'role' => 'admin']);
        $summary = $this->summary();

        $this->actingAs($foreignAdmin)->withHeader('X-Workspace-Id', $this->workspace->id)
            ->postJson('/api/settings/commands/clear_conversation_summaries')->assertForbidden();
        $this->assertNotNull($summary->fresh());
    }

    public function test_unknown_commands_are_rejected(): void
    {
        $this->actingAs($this->owner)->postJson('/api/settings/commands/drop_everything')->assertNotFound();
        $this->actingAs($this->owner)->postJson('/api/settings/danger-action', ['action' => 'drop_everything'])->assertStatus(422);
    }

    public function test_shared_logs_are_platform_operator_only(): void
    {
        $this->actingAs($this->admin)->getJson('/api/settings/debug')
            ->assertOk()->assertJsonPath('logs', [])->assertJsonPath('logs_scope', 'hidden');

        config(['authorization.platform_operator_emails' => [strtoupper((string) $this->admin->email)]]);
        $this->actingAs($this->admin)->getJson('/api/settings/debug')->assertOk()->assertJsonPath('logs_scope', 'platform');
    }

    private function human(string $role): User
    {
        $user = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function summary(): ConversationSummary
    {
        $agent = User::factory()->agent()->create(['workspace_id' => $this->workspace->id]);
        $channel = \App\Models\Channel::factory()->create(['workspace_id' => $this->workspace->id]);

        return ConversationSummary::forceCreate([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'channel_id' => $channel->id,
            'agent_id' => $agent->id,
            'summary' => 'old talk',
            'tokens_before' => 10,
            'tokens_after' => 5,
            'compaction_count' => 1,
            'messages_summarized' => 3,
        ]);
    }
}
