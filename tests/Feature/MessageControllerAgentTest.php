<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Events\MessageSent;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageControllerAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_to_agent_triggers_async_response(): void
    {
        OpenCompanyAgent::fake(['I can help with that!']);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);

        // Create DM channel
        $channel = Channel::factory()->create(['type' => 'dm']);
        DirectMessage::create([
            'id' => 'dm-1',
            'channel_id' => $channel->id,
            'user1_id' => $human->id,
            'user2_id' => $agent->id,
        ]);

        // Send message via API (queue is sync in test, so job executes immediately)
        $response = $this->actingAs($human)->postJson('/api/messages', [
            'content' => 'Can you help me?',
            'channelId' => $channel->id,
            'authorId' => $human->id,
        ]);

        $response->assertSuccessful();

        // The agent should have responded (sync queue)
        $agentMessages = Message::where('author_id', $agent->id)->get();
        $this->assertGreaterThanOrEqual(1, $agentMessages->count());
    }

    public function test_message_in_public_channel_does_not_trigger_agent(): void
    {
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $channel = Channel::factory()->create(['type' => 'public']);

        $response = $this->actingAs($human)->postJson('/api/messages', [
            'content' => 'Hello everyone',
            'channelId' => $channel->id,
            'authorId' => $human->id,
        ]);

        $response->assertSuccessful();

        // Only the human's message should exist
        $this->assertEquals(1, Message::count());
    }

    public function test_dm_between_humans_does_not_trigger_agent(): void
    {
        Event::fake([MessageSent::class]);

        $human1 = User::factory()->create(['type' => 'human']);
        $human2 = User::factory()->create(['type' => 'human']);

        $channel = Channel::factory()->create(['type' => 'dm']);
        DirectMessage::create([
            'id' => 'dm-1',
            'channel_id' => $channel->id,
            'user1_id' => $human1->id,
            'user2_id' => $human2->id,
        ]);

        $response = $this->actingAs($human1)->postJson('/api/messages', [
            'content' => 'Hey, how are you?',
            'channelId' => $channel->id,
            'authorId' => $human1->id,
        ]);

        $response->assertSuccessful();
        $this->assertEquals(1, Message::count());
    }

    public function test_agent_dm_endpoint_repairs_orphan_channel_metadata(): void
    {
        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create(['status' => 'idle']);
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $human->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $agent->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($human)->getJson("/api/dm/{$agent->id}");

        $response->assertSuccessful()
            ->assertJsonPath('channelId', $channel->id);

        $this->assertDatabaseHas('direct_messages', [
            'channel_id' => $channel->id,
            'user1_id' => $human->id,
            'user2_id' => $agent->id,
        ]);
    }

    public function test_agent_dm_endpoint_prefers_existing_metadata_over_orphan_duplicate(): void
    {
        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create(['status' => 'idle']);
        $canonicalChannel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);
        $orphanChannel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);

        foreach ([$canonicalChannel, $orphanChannel] as $channel) {
            ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $human->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);
            ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $agent->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        DirectMessage::create([
            'id' => 'dm-existing',
            'channel_id' => $canonicalChannel->id,
            'user1_id' => $human->id,
            'user2_id' => $agent->id,
        ]);

        $response = $this->actingAs($human)->getJson("/api/dm/{$agent->id}");

        $response->assertSuccessful()
            ->assertJsonPath('channelId', $canonicalChannel->id);

        $this->assertSame(1, DirectMessage::query()
            ->whereIn('channel_id', [$canonicalChannel->id, $orphanChannel->id])
            ->count());
    }

    public function test_orphan_dm_channel_with_single_agent_member_repairs_and_dispatches_agent(): void
    {
        Queue::fake([AgentRespondJob::class]);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create(['status' => 'idle']);
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);

        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $human->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $agent->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($human)->postJson('/api/messages', [
            'content' => 'Run an accessibility audit.',
            'channelId' => $channel->id,
            'authorId' => $human->id,
        ]);

        $response->assertSuccessful();

        $messageId = $response->json('id');

        $this->assertDatabaseHas('direct_messages', [
            'channel_id' => $channel->id,
            'user1_id' => $human->id,
            'user2_id' => $agent->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'trigger_message_id' => $messageId,
            'status' => Task::STATUS_PENDING,
        ]);
        Queue::assertPushed(AgentRespondJob::class);
    }

    public function test_dm_store_endpoint_creates_missing_conversation_and_dispatches_agent(): void
    {
        Queue::fake([AgentRespondJob::class]);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create(['status' => 'idle']);

        $response = $this->actingAs($human)->postJson("/api/dm/{$agent->id}", [
            'content' => 'Start from a fresh DM endpoint call.',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('agentMessage', null);

        $dm = DirectMessage::query()
            ->where('user1_id', $human->id)
            ->where('user2_id', $agent->id)
            ->firstOrFail();

        $this->assertDatabaseHas('tasks', [
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $dm->channel_id,
            'status' => Task::STATUS_PENDING,
        ]);
        Queue::assertPushed(AgentRespondJob::class);
    }

    public function test_orphan_dm_channel_with_ambiguous_agent_members_does_not_dispatch_agent(): void
    {
        Queue::fake([AgentRespondJob::class]);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $firstAgent = User::factory()->agent()->create(['status' => 'idle']);
        $secondAgent = User::factory()->agent()->create(['status' => 'idle']);
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);

        foreach ([$human->id, $firstAgent->id, $secondAgent->id] as $memberId) {
            ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $memberId,
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        $response = $this->actingAs($human)->postJson('/api/messages', [
            'content' => 'Run an accessibility audit.',
            'channelId' => $channel->id,
            'authorId' => $human->id,
        ]);

        $response->assertSuccessful();

        $this->assertDatabaseMissing('direct_messages', [
            'channel_id' => $channel->id,
        ]);
        $this->assertDatabaseMissing('tasks', [
            'channel_id' => $channel->id,
        ]);
        Queue::assertNotPushed(AgentRespondJob::class);
    }
}
