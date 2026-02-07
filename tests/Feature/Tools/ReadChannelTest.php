<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Chat\ReadChannel;
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

        $tool = new ReadChannel($agent, app(AgentPermissionService::class));
        $request = new Request(['channelId' => $channel->id]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Recent messages in #general', $result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('Hello everyone!', $result);
        $this->assertStringContainsString('How is it going?', $result);
    }

    public function test_returns_error_for_nonexistent_channel(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $tool = new ReadChannel($agent, app(AgentPermissionService::class));
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

        $tool = new ReadChannel($agent, app(AgentPermissionService::class));
        $request = new Request(['channelId' => $channel->id, 'action' => 'pinned']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Pinned messages in #announcements', $result);
        $this->assertStringContainsString('Important pinned message', $result);
        $this->assertStringNotContainsString('Regular unpinned message', $result);
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

        $tool = new ReadChannel($agent, app(AgentPermissionService::class));
        $request = new Request([
            'channelId' => $channel->id,
            'action' => 'thread',
            'messageId' => $parent->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Thread for message by Carol', $result);
        $this->assertStringContainsString('Parent message here', $result);
        $this->assertStringContainsString('Replies (2)', $result);
        $this->assertStringContainsString('This is a reply', $result);
        $this->assertStringContainsString('Another reply', $result);
        $this->assertStringContainsString('Dave', $result);
    }

    public function test_has_correct_description(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new ReadChannel($agent, app(AgentPermissionService::class));

        $this->assertStringContainsString('Read messages from a channel', $tool->description());
    }
}
