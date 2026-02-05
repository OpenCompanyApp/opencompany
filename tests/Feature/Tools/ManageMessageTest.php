<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\ManageMessage;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ManageMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_message(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create();
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $agent->id,
            'content' => 'Message to delete',
        ]);

        $tool = new ManageMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => $message->id, 'action' => 'delete']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Message deleted', $result);
        $this->assertNull(Message::find($message->id));
    }

    public function test_pins_message(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create();
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $agent->id,
            'content' => 'Pin this message',
            'is_pinned' => false,
        ]);

        $tool = new ManageMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => $message->id, 'action' => 'pin']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Message pinned', $result);

        $message->refresh();
        $this->assertTrue($message->is_pinned);
        $this->assertNotNull($message->pinned_at);
        $this->assertEquals($agent->id, $message->pinned_by_id);
    }

    public function test_unpins_already_pinned_message(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create();
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $agent->id,
            'content' => 'Already pinned message',
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by_id' => $agent->id,
        ]);

        $tool = new ManageMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => $message->id, 'action' => 'pin']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Message unpinned', $result);

        $message->refresh();
        $this->assertFalse($message->is_pinned);
        $this->assertNull($message->pinned_at);
        $this->assertNull($message->pinned_by_id);
    }

    public function test_adds_reaction(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create();
        $message = Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $agent->id,
            'content' => 'React to this',
        ]);

        $tool = new ManageMessage($agent, app(AgentPermissionService::class));
        $request = new Request([
            'messageId' => $message->id,
            'action' => 'add_reaction',
            'emoji' => '👍',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Reaction added', $result);

        $reaction = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $agent->id)
            ->first();

        $this->assertNotNull($reaction);
        $this->assertEquals('👍', $reaction->emoji);
    }

    public function test_returns_error_for_nonexistent_message(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $tool = new ManageMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => 'nonexistent-uuid', 'action' => 'delete']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('not found', $result);
    }

    public function test_has_correct_description(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new ManageMessage($agent, app(AgentPermissionService::class));

        $this->assertStringContainsString('Manage messages', $tool->description());
    }
}
