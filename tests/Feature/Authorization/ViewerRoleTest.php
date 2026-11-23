<?php

namespace Tests\Feature\Authorization;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The fixed `viewer` role (GIN-1197) is read-only across the whole API: the
 * workspace write guard refuses every state-changing request before a
 * controller runs, except the viewer's own read state. Admins can assign it.
 */
class ViewerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_reads_but_never_writes(): void
    {
        $viewer = User::factory()->create(['type' => 'human']);
        $membership = WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        $channel = Channel::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'name' => 'general', 'type' => 'public']);
        ChannelMember::create(['channel_id' => $channel->id, 'user_id' => $viewer->id, 'role' => 'member']);

        $this->actingAs($viewer)->getJson('/api/channels')->assertOk();
        $this->actingAs($viewer)->getJson("/api/messages?channelId={$channel->id}")->assertOk();

        $this->actingAs($viewer)->postJson('/api/messages', ['channelId' => $channel->id, 'content' => 'hi'])
            ->assertForbidden()->assertJsonPath('error', 'role_read_only');
        $this->actingAs($viewer)->postJson('/api/tasks', ['title' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/tables', ['name' => 'x'])->assertForbidden();
        $this->assertSame(0, Task::count());

        // Own read state is not workspace content.
        $this->actingAs($viewer)->postJson("/api/channels/{$channel->id}/read")->assertOk();

        // Admins assign the role through the members API.
        $admin = User::factory()->create(['type' => 'human']);
        $this->actingAs($admin)->patchJson("/api/workspace/members/{$membership->id}/role", ['role' => 'member'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/workspace/members/{$membership->id}/role", ['role' => 'viewer'])->assertOk();
        $this->assertSame('viewer', $membership->fresh()->role);
    }
}
