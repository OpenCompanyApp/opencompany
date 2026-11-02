<?php

namespace Tests\Feature\Security;

use App\Events\AgentStatusUpdated;
use App\Events\ApprovalCreated;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * UI-audit finding: approval and agent-status events were broadcast on PUBLIC
 * channels, so routes/channels.php never ran and anyone with the Reverb key
 * and a workspace id could subscribe. They must be private, and the channel
 * authorization must admit workspace members only.
 */
class WorkspaceBroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A signing broadcaster so /broadcasting/auth really evaluates the
        // channel callbacks (the null driver authorizes nothing).
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        app('Illuminate\Broadcasting\BroadcastManager')->forgetDrivers();
        require base_path('routes/channels.php');
    }

    public function test_approval_and_agent_status_events_use_private_channels(): void
    {
        $agent = User::factory()->agent()->create();
        $approval = ApprovalRequest::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id,
            'type' => 'action', 'title' => 'Secret plan', 'description' => 'Wire funds', 'requester_id' => $agent->id, 'status' => 'pending']);

        foreach ([new AgentStatusUpdated($agent), new ApprovalCreated($approval)] as $event) {
            foreach ($event->broadcastOn() as $channel) {
                $this->assertInstanceOf(PrivateChannel::class, $channel, $event::class);
            }
        }
    }

    public function test_only_workspace_members_can_subscribe(): void
    {
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $member->id, 'role' => 'member']);
        $outsider = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => Workspace::create(['name' => 'B', 'slug' => 'b-bcast'])->id,
            'user_id' => $outsider->id, 'role' => 'admin']);

        foreach (['approvals', 'agents'] as $suffix) {
            $payload = ['socket_id' => '123.456', 'channel_name' => "private-workspace.{$this->workspace->id}.{$suffix}"];
            // be(), not actingAs(): the base TestCase::actingAs() auto-joins
            // the user to the test workspace, which would hide the leak.
            $this->be($member)->postJson('/broadcasting/auth', $payload)->assertOk();
            $this->be($outsider)->postJson('/broadcasting/auth', $payload)->assertForbidden();
        }
    }

    // Humans join workspaces only through workspace_members. The users
    // workspace_id column (agent ownership / stale primary workspace) must
    // not admit a human who is not a member.
    public function test_human_workspace_id_column_does_not_grant_channel_access(): void
    {
        $exMember = User::factory()->create(['workspace_id' => $this->workspace->id]);
        $payload = ['socket_id' => '123.456', 'channel_name' => "private-workspace.{$this->workspace->id}.approvals"];

        $this->be($exMember)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    }
}
