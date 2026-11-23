<?php

namespace Tests\Feature\Authorization;

use App\Models\AgentPermission;
use App\Models\AuthorizationLedgerEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Explicit agent-management and delegation capabilities (GIN-1201; ROADMAP
 * P0 item 6). Workspace membership alone no longer lets someone edit, delete
 * or re-grant any agent: admins manage every agent, a member manages the
 * agents they manage, only admins change grants or managers, viewers change
 * nothing, and agent ids from another workspace are a 404.
 */
class AgentManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $manager;

    private User $member;

    private User $viewer;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->human('admin');
        $this->workspace->update(['owner_id' => $this->owner->id]);
        $this->admin = $this->human('admin');
        $this->manager = $this->human('member');
        $this->member = $this->human('member');
        $this->viewer = $this->human('viewer');
        $this->agent = User::factory()->agent()->create(['workspace_id' => $this->workspace->id, 'manager_id' => $this->manager->id, 'name' => 'Ada']);
    }

    public function test_profile_edits_follow_the_manager_relationship(): void
    {
        $this->actingAs($this->member)->patchJson("/api/agents/{$this->agent->id}", ['name' => 'Hijacked'])
            ->assertForbidden()->assertJsonPath('error', 'agent_manager_required');
        $this->actingAs($this->viewer)->patchJson("/api/agents/{$this->agent->id}", ['name' => 'Hijacked'])
            ->assertForbidden()->assertJsonPath('error', 'role_read_only');
        $this->assertSame('Ada', $this->agent->fresh()->name);

        $this->actingAs($this->manager)->patchJson("/api/agents/{$this->agent->id}", ['name' => 'Ada 2'])->assertOk();
        $this->actingAs($this->owner)->patchJson("/api/agents/{$this->agent->id}", ['name' => 'Ada 3'])->assertOk();
        $this->assertSame('Ada 3', $this->agent->fresh()->name);
    }

    public function test_identity_edits_and_deletion_need_the_manager_or_an_admin(): void
    {
        $this->actingAs($this->member)->putJson("/api/agents/{$this->agent->id}/identity/IDENTITY", ['content' => 'evil'])
            ->assertForbidden();
        $this->actingAs($this->member)->deleteJson("/api/agents/{$this->agent->id}")->assertForbidden();
        $this->assertNotNull($this->agent->fresh());
    }

    public function test_only_admins_change_grants_and_every_change_is_ledgered(): void
    {
        $payload = ['tools' => [['scopeKey' => 'send_channel_message', 'permission' => 'allow', 'requiresApproval' => false]]];

        $this->actingAs($this->manager)->putJson("/api/agents/{$this->agent->id}/permissions/tools", $payload)
            ->assertForbidden()->assertJsonPath('error', 'capability_required');
        $this->actingAs($this->manager)->putJson("/api/agents/{$this->agent->id}/permissions/channels", ['channels' => []])
            ->assertForbidden();
        $this->assertSame(0, AgentPermission::forAgent($this->agent->id)->count());

        $this->actingAs($this->admin)->putJson("/api/agents/{$this->agent->id}/permissions/tools", $payload)->assertOk();

        $change = AuthorizationLedgerEntry::query()->where('action', 'agent.grants.replace')->sole();
        $this->assertSame(AuthorizationLedgerEntry::KIND_SECURITY_CHANGE, $change->kind);
        $this->assertSame($this->admin->id, $change->actor_id);
        $this->assertSame(['send_channel_message:allow'], $change->details['after']);
        $this->assertTrue(AuthorizationLedgerEntry::query()->where('action', 'agent.update_grants')->where('effect', 'deny')->exists());
    }

    public function test_manager_reassignment_needs_an_admin_and_a_workspace_target(): void
    {
        $this->actingAs($this->manager)->patchJson("/api/agents/{$this->agent->id}", ['managerId' => $this->member->id])
            ->assertForbidden()->assertJsonPath('error', 'capability_required');

        $foreign = User::factory()->create(['type' => 'human']);
        $this->actingAs($this->admin)->patchJson("/api/agents/{$this->agent->id}", ['managerId' => $foreign->id])->assertStatus(422);
        $this->actingAs($this->admin)->patchJson("/api/agents/{$this->agent->id}", ['managerId' => $this->agent->id])->assertStatus(422);

        $this->actingAs($this->admin)->patchJson("/api/agents/{$this->agent->id}", ['managerId' => $this->member->id])->assertOk();
        $this->assertSame($this->member->id, $this->agent->fresh()->manager_id);
    }

    public function test_creation_cannot_assign_someone_else_as_manager_without_admin(): void
    {
        $body = ['name' => 'Bot', 'agentType' => 'general', 'brain' => 'openai:gpt-4o', 'managerId' => $this->manager->id];

        $this->actingAs($this->member)->postJson('/api/agents', $body)
            ->assertForbidden()->assertJsonPath('error', 'agent_manager_assignment_requires_admin');
        $this->actingAs($this->viewer)->postJson('/api/agents', [...$body, 'managerId' => $this->viewer->id])->assertForbidden();
        $this->assertSame(1, User::where('type', 'agent')->count());
    }

    public function test_agents_from_another_workspace_are_not_found(): void
    {
        $other = Workspace::create(['id' => (string) Str::uuid(), 'name' => 'B', 'slug' => 'b-'.Str::random(5)]);
        $foreign = User::factory()->agent()->create(['workspace_id' => $other->id]);

        $this->actingAs($this->owner)->patchJson("/api/agents/{$foreign->id}", ['name' => 'x'])->assertNotFound();
        $this->actingAs($this->owner)->deleteJson("/api/agents/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->owner)->putJson("/api/agents/{$foreign->id}/permissions/tools", ['tools' => []])->assertNotFound();
    }

    private function human(string $role): User
    {
        $user = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
