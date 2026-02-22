<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Chat\AddMessageReaction;
use App\Agents\Tools\Chat\DeleteMessage;
use App\Agents\Tools\Chat\PinMessage;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $tool = new DeleteMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => $message->id]);

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

        $tool = new PinMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => $message->id]);

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

        $tool = new PinMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => $message->id]);

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

        $tool = new AddMessageReaction($agent, app(AgentPermissionService::class));
        $request = new Request([
            'messageId' => $message->id,
            'emoji' => "\u{1F44D}",
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Reaction added', $result);

        $reaction = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $agent->id)
            ->first();

        $this->assertNotNull($reaction);
        $this->assertEquals("\u{1F44D}", $reaction->emoji);
    }

    public function test_returns_error_for_nonexistent_message(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $tool = new DeleteMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['messageId' => 'nonexistent-uuid']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('not found', $result);
    }

    public function test_has_correct_descriptions(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $permissionService = app(AgentPermissionService::class);

        $deleteTool = new DeleteMessage($agent, $permissionService);
        $pinTool = new PinMessage($agent, $permissionService);
        $reactionTool = new AddMessageReaction($agent, $permissionService);

        $this->assertStringContainsString('Delete a message', $deleteTool->description());
        $this->assertStringContainsString('pinned status', $pinTool->description());
        $this->assertStringContainsString('emoji reaction', $reactionTool->description());
    }
}
