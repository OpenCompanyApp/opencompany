<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
}
