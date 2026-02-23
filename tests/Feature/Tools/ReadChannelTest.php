<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Chat\ReadPinnedMessages;
use App\Agents\Tools\Chat\ReadRecentMessages;
use App\Agents\Tools\Chat\ReadThread;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ReadChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_recent_messages_from_channel(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create(['name' => 'general']);
        $author = User::factory()->create(['name' => 'Alice']);

        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $author->id,
            'content' => 'Hello everyone!',
        ]);

        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $author->id,
            'content' => 'How is it going?',
        ]);

        $tool = new ReadRecentMessages($agent, app(AgentPermissionService::class));
        $request = new Request(['channelId' => $channel->id]);

        $result = $tool->handle($request);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertStringContainsString('Alice', json_encode($decoded));
        $this->assertStringContainsString('Hello everyone!', json_encode($decoded));
        $this->assertStringContainsString('How is it going?', json_encode($decoded));
    }

    public function test_returns_error_for_nonexistent_channel(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $tool = new ReadRecentMessages($agent, app(AgentPermissionService::class));
        $request = new Request(['channelId' => 'nonexistent-uuid']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('not found', $result);
    }

    public function test_reads_pinned_messages(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create(['name' => 'announcements']);
        $author = User::factory()->create(['name' => 'Bob']);

        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $author->id,
            'content' => 'Important pinned message',
            'is_pinned' => true,
            'pinned_at' => now(),
        ]);

        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $author->id,
            'content' => 'Regular unpinned message',
            'is_pinned' => false,
        ]);

        $tool = new ReadPinnedMessages($agent, app(AgentPermissionService::class));
        $request = new Request(['channelId' => $channel->id]);

        $result = $tool->handle($request);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertStringContainsString('Important pinned message', json_encode($decoded));
        $this->assertStringNotContainsString('Regular unpinned message', json_encode($decoded));
    }

    public function test_reads_thread_replies(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create(['name' => 'dev']);
        $author = User::factory()->create(['name' => 'Carol']);
        $replier = User::factory()->create(['name' => 'Dave']);

        $parent = Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $author->id,
            'content' => 'Parent message here',
        ]);

        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $replier->id,
            'content' => 'This is a reply',
            'reply_to_id' => $parent->id,
        ]);

        Message::factory()->create([
            'channel_id' => $channel->id,
            'author_id' => $author->id,
            'content' => 'Another reply',
            'reply_to_id' => $parent->id,
        ]);

        $tool = new ReadThread($agent, app(AgentPermissionService::class));
        $request = new Request([
            'channelId' => $channel->id,
            'messageId' => $parent->id,
        ]);

        $result = $tool->handle($request);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('Carol', $decoded['parent']['author']);
        $this->assertStringContainsString('Parent message here', $decoded['parent']['content']);
        $this->assertCount(2, $decoded['replies']);
        $this->assertStringContainsString('This is a reply', json_encode($decoded['replies']));
        $this->assertStringContainsString('Another reply', json_encode($decoded['replies']));
        $this->assertStringContainsString('Dave', json_encode($decoded['replies']));
    }

    public function test_has_correct_descriptions(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $permissionService = app(AgentPermissionService::class);

        $recentTool = new ReadRecentMessages($agent, $permissionService);
        $threadTool = new ReadThread($agent, $permissionService);
        $pinnedTool = new ReadPinnedMessages($agent, $permissionService);

        $this->assertStringContainsString('Read recent messages', $recentTool->description());
        $this->assertStringContainsString('thread', $threadTool->description());
        $this->assertStringContainsString('pinned messages', $pinnedTool->description());
    }
}
