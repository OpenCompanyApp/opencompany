<?php

namespace Tests\Feature\Authorization;

use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DmController used to accept arbitrary target ids. Opening a DM now goes
 * through DirectMessageResolver::openForActor(): the authenticated user is
 * always a participant, self-DMs are rejected (422), and targets outside the
 * active workspace (or unknown ids) are a 404 without creating any channel
 * or direct_messages metadata.
 */
class DirectMessageAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_with_self_is_rejected_without_creating_a_channel(): void
    {
        $user = User::factory()->create(['type' => 'human']);

        $this->actingAs($user)->getJson("/api/dm/{$user->id}")->assertUnprocessable();
        $this->actingAs($user)->postJson("/api/dm/{$user->id}", ['content' => 'hi me'])->assertUnprocessable();

        $this->assertSame(0, DirectMessage::count());
        $this->assertSame(0, Channel::where('type', 'dm')->count());
    }

    public function test_dm_with_foreign_or_unknown_user_is_404_without_side_effects(): void
    {
        Queue::fake();
        $user = User::factory()->create(['type' => 'human']);

        $other = Workspace::create(['id' => Str::uuid()->toString(), 'name' => 'B', 'slug' => 'b']);
        $foreignHuman = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $foreignHuman->id, 'role' => 'member']);
        $foreignAgent = User::factory()->agent()->create(['workspace_id' => $other->id]);

        foreach ([$foreignHuman->id, $foreignAgent->id, 'does-not-exist'] as $targetId) {
            $this->actingAs($user)->getJson("/api/dm/{$targetId}")->assertNotFound();
            $this->actingAs($user)->postJson("/api/dm/{$targetId}", ['content' => 'leak?'])->assertNotFound();
        }

        $this->assertSame(0, DirectMessage::count());
        $this->assertSame(0, Channel::where('type', 'dm')->count());
        Queue::assertNothingPushed();
    }

    public function test_dm_with_workspace_peer_is_created_once(): void
    {
        $user = User::factory()->create(['type' => 'human']);
        $peer = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $peer->id, 'role' => 'member']);

        $this->actingAs($user)->getJson("/api/dm/{$peer->id}")
            ->assertOk()
            ->assertJsonPath('otherUser.id', $peer->id);
        $this->actingAs($user)->getJson("/api/dm/{$peer->id}")->assertOk();

        $this->assertSame(1, DirectMessage::where('workspace_id', $this->workspace->id)->count());
    }
}
