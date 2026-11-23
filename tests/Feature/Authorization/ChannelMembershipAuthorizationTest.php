<?php

namespace Tests\Feature\Authorization;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Channel membership for channel and message reads and writes (GIN-1198,
 * GIN-1146; ROADMAP P0 item 2). A same-workspace non-member must not
 * discover or touch a private channel or someone else's DM through any
 * endpoint, forged ids included, and omitting channelId must not widen the
 * message list. Workspace admins have no read override on private
 * conversations.
 */
class ChannelMembershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;   // member of the private channel

    private User $bob;     // same workspace, not a member

    private User $admin;   // workspace admin, not a member

    private Channel $private;

    private Channel $public;

    private Message $secret;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->alice = $this->human('member');
        $this->bob = $this->human('member');
        $this->admin = $this->human('admin');

        $this->private = $this->channel('private', [$this->alice]);
        $this->public = $this->channel('public', [$this->alice]);
        $this->secret = $this->message($this->private, $this->alice, 'quarterly numbers');
        $this->message($this->public, $this->alice, 'hello all');
    }

    public function test_non_member_cannot_list_read_or_search_a_private_channel(): void
    {
        $ids = collect($this->actingAs($this->bob)->getJson('/api/channels')->assertOk()->json())->pluck('id');
        $this->assertContains($this->public->id, $ids);
        $this->assertNotContains($this->private->id, $ids);

        $this->actingAs($this->bob)->getJson("/api/channels/{$this->private->id}")->assertNotFound();
        $this->actingAs($this->bob)->getJson("/api/channels/{$this->private->id}/pinned")->assertNotFound();
        $this->actingAs($this->bob)->getJson("/api/messages?channelId={$this->private->id}")->assertNotFound();
        $this->actingAs($this->bob)->getJson("/api/messages/{$this->secret->id}/thread")->assertNotFound();

        // Omitting channelId only returns messages of readable channels.
        $contents = collect($this->actingAs($this->bob)->getJson('/api/messages')->assertOk()->json())->pluck('content');
        $this->assertContains('hello all', $contents);
        $this->assertNotContains('quarterly numbers', $contents);

        $search = $this->actingAs($this->bob)->getJson('/api/search?q=quarterly&type=messages')->assertOk()->json('messages');
        $this->assertSame([], $search);
    }

    public function test_workspace_admin_has_no_read_override_on_private_channels(): void
    {
        $this->actingAs($this->admin)->getJson("/api/channels/{$this->private->id}")->assertNotFound();
        $this->actingAs($this->admin)->getJson("/api/messages?channelId={$this->private->id}")->assertNotFound();
    }

    public function test_member_reads_and_posts_in_a_private_channel(): void
    {
        $this->actingAs($this->alice)->getJson("/api/channels/{$this->private->id}")->assertOk();
        $this->actingAs($this->alice)->getJson("/api/messages?channelId={$this->private->id}")
            ->assertOk()->assertJsonFragment(['content' => 'quarterly numbers']);
        $this->actingAs($this->alice)->postJson('/api/messages', ['channelId' => $this->private->id, 'content' => 'more'])
            ->assertSuccessful();
    }

    public function test_non_member_writes_are_refused_before_any_side_effect(): void
    {
        $before = Message::count();

        $this->actingAs($this->bob)->postJson('/api/messages', ['channelId' => $this->private->id, 'content' => 'sneak'])->assertNotFound();
        $this->actingAs($this->bob)->postJson("/api/messages/{$this->secret->id}/reactions", ['emoji' => '👀'])->assertNotFound();
        $this->actingAs($this->bob)->postJson("/api/messages/{$this->secret->id}/pin")->assertNotFound();
        $this->actingAs($this->bob)->deleteJson("/api/messages/{$this->secret->id}")->assertNotFound();
        $this->actingAs($this->bob)->postJson("/api/channels/{$this->private->id}/members", ['userId' => $this->bob->id])->assertNotFound();
        $this->actingAs($this->bob)->postJson("/api/channels/{$this->private->id}/read")->assertNotFound();

        $this->assertSame($before, Message::count());
        $this->assertFalse($this->secret->fresh()->is_pinned);
        $this->assertFalse(ChannelMember::where('channel_id', $this->private->id)->where('user_id', $this->bob->id)->exists());
        Queue::assertNothingPushed();
    }

    public function test_messages_can_only_be_deleted_by_their_author_or_a_moderator(): void
    {
        $alicesPublic = $this->message($this->public, $this->alice, 'mine');

        $this->actingAs($this->bob)->deleteJson("/api/messages/{$alicesPublic->id}")
            ->assertForbidden()->assertJsonPath('error', 'message_owner_required');
        $this->assertNotNull($alicesPublic->fresh());

        // Workspace admins moderate public channels.
        $this->actingAs($this->admin)->deleteJson("/api/messages/{$alicesPublic->id}")->assertOk();
        $this->assertNull($alicesPublic->fresh());
    }

    public function test_reactions_can_only_be_removed_by_their_owner(): void
    {
        $message = $this->message($this->public, $this->alice, 'react to me');
        $reaction = MessageReaction::create(['id' => (string) Str::uuid(), 'message_id' => $message->id, 'user_id' => $this->alice->id, 'emoji' => '👍']);

        $this->actingAs($this->bob)->deleteJson("/api/messages/{$message->id}/reactions/{$reaction->id}")
            ->assertForbidden()->assertJsonPath('error', 'reaction_owner_required');
        $this->actingAs($this->alice)->deleteJson("/api/messages/{$message->id}/reactions/{$reaction->id}")->assertOk();
        $this->assertNull($reaction->fresh());
    }

    public function test_channel_membership_changes_need_a_channel_admin(): void
    {
        $carol = $this->human('member');
        $this->channelMember($this->public, $this->bob, 'member');

        // A plain channel member cannot add someone else.
        $this->actingAs($this->bob)->postJson("/api/channels/{$this->public->id}/members", ['userId' => $carol->id])
            ->assertForbidden()->assertJsonPath('error', 'channel_admin_required');

        // Joining a public channel yourself is allowed.
        $this->actingAs($carol)->postJson("/api/channels/{$this->public->id}/members", ['userId' => $carol->id])->assertSuccessful();

        // The channel admin cannot add someone from another workspace.
        $foreign = User::factory()->create(['type' => 'human']);
        $this->actingAs($this->alice)->postJson("/api/channels/{$this->public->id}/members", ['userId' => $foreign->id])
            ->assertForbidden()->assertJsonPath('error', 'target_not_in_workspace');

        // Removing someone else needs channel admin; leaving yourself does not.
        $this->actingAs($this->bob)->deleteJson("/api/channels/{$this->public->id}/members/{$carol->id}")->assertForbidden();
        $this->actingAs($carol)->deleteJson("/api/channels/{$this->public->id}/members/{$carol->id}")->assertOk();
    }

    public function test_channel_creation_ignores_a_forged_creator_and_rejects_outside_members(): void
    {
        $foreign = User::factory()->create(['type' => 'human']);
        $this->actingAs($this->bob)->postJson('/api/channels', ['name' => 'x', 'type' => 'private', 'memberIds' => [$foreign->id]])
            ->assertStatus(422);

        $created = $this->actingAs($this->bob)
            ->postJson('/api/channels', ['name' => 'team', 'type' => 'private', 'creatorId' => $this->alice->id, 'memberIds' => [$this->alice->id]])
            ->assertSuccessful()->json();

        $this->assertSame($this->bob->id, $created['creator_id']);
        $this->assertSame('admin', ChannelMember::where('channel_id', $created['id'])->where('user_id', $this->bob->id)->value('role'));
    }

    public function test_dm_endpoints_ignore_forged_participants(): void
    {
        $dmChannel = Channel::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'name' => 'DM', 'type' => 'dm']);
        $dm = DirectMessage::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'user1_id' => $this->alice->id, 'user2_id' => $this->admin->id, 'channel_id' => $dmChannel->id]);
        $this->channelMember($dmChannel, $this->alice, 'member');
        $this->channelMember($dmChannel, $this->admin, 'member');
        $this->message($dmChannel, $this->alice, 'just between us');

        // index ignores the forged userId and lists only the caller's DMs.
        $this->assertSame([], $this->actingAs($this->bob)->getJson("/api/direct-messages?userId={$this->alice->id}")->assertOk()->json());
        $this->actingAs($this->bob)->getJson("/api/direct-messages/{$dm->id}")->assertNotFound();
        $this->actingAs($this->bob)->postJson("/api/direct-messages/{$dm->id}/read")->assertNotFound();
        $this->actingAs($this->bob)->getJson("/api/messages?channelId={$dmChannel->id}")->assertNotFound();

        // Creating a DM between two other people is refused.
        $this->actingAs($this->bob)->postJson('/api/direct-messages', ['user1Id' => $this->alice->id, 'user2Id' => $this->admin->id])
            ->assertForbidden();
        $this->assertSame(1, DirectMessage::count());

        // The participant still works.
        $this->actingAs($this->alice)->getJson("/api/direct-messages/{$dm->id}")->assertOk();
    }

    public function test_foreign_workspace_channels_are_invisible(): void
    {
        $other = Workspace::create(['id' => (string) Str::uuid(), 'name' => 'B', 'slug' => 'b-'.Str::random(5)]);
        $foreign = Channel::create(['id' => (string) Str::uuid(), 'workspace_id' => $other->id, 'name' => 'x', 'type' => 'public']);
        $foreignMessage = $this->message($foreign, $this->alice, 'elsewhere');

        $this->actingAs($this->alice)->getJson("/api/channels/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->alice)->getJson("/api/messages/{$foreignMessage->id}/thread")->assertNotFound();
        $this->assertNotContains('elsewhere', collect($this->actingAs($this->alice)->getJson('/api/messages')->json())->pluck('content'));
    }

    private function human(string $role): User
    {
        $user = User::factory()->create(['type' => 'human']);
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /** @param  list<User>  $members */
    private function channel(string $type, array $members): Channel
    {
        $channel = Channel::create(['id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'name' => $type.'-'.Str::random(4), 'type' => $type]);
        foreach ($members as $i => $member) {
            $this->channelMember($channel, $member, $i === 0 ? 'admin' : 'member');
        }

        return $channel;
    }

    private function channelMember(Channel $channel, User $user, string $role): void
    {
        ChannelMember::create(['channel_id' => $channel->id, 'user_id' => $user->id, 'role' => $role]);
    }

    private function message(Channel $channel, User $author, string $content): Message
    {
        return Message::create(['id' => (string) Str::uuid(), 'channel_id' => $channel->id, 'author_id' => $author->id, 'content' => $content, 'timestamp' => now()]);
    }
}
