<?php

namespace Tests\Feature;

use App\Agents\Conversations\ChannelConversationLoader;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Tests\TestCase;

class ChannelConversationLoaderTest extends TestCase
{
    use RefreshDatabase;

    private ChannelConversationLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new ChannelConversationLoader();
    }

    public function test_loads_messages_as_sdk_objects(): void
    {
        $human = User::factory()->create(['name' => 'Alice', 'type' => 'human']);
        $agent = User::factory()->create(['name' => 'Logic', 'type' => 'agent']);
        $channel = Channel::factory()->create();

        Message::create([
            'id' => 'msg-1',
            'content' => 'Hello agent',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        Message::create([
            'id' => 'msg-2',
            'content' => 'Hello! How can I help?',
            'channel_id' => $channel->id,
            'author_id' => $agent->id,
            'timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messages = $this->loader->load($channel->id, $agent);

        $this->assertCount(2, $messages);

        // First message (older) is from human -> UserMessage
        // Second message (newer) is from agent -> AssistantMessage
        $userMsg = collect($messages)->first(fn ($m) => $m instanceof UserMessage);
        $assistantMsg = collect($messages)->first(fn ($m) => $m instanceof AssistantMessage);

        $this->assertNotNull($userMsg, 'Expected a UserMessage from human');
        $this->assertNotNull($assistantMsg, 'Expected an AssistantMessage from agent');
        $this->assertStringContains('[Alice]:', $userMsg->content);
        $this->assertEquals('Hello! How can I help?', $assistantMsg->content);
    }

    public function test_skips_empty_messages(): void
    {
        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create();

        Message::create([
            'id' => 'msg-1',
            'content' => '',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        Message::create([
            'id' => 'msg-2',
            'content' => 'Real message',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        $messages = $this->loader->load($channel->id, $agent);

        $this->assertCount(1, $messages);
    }

    public function test_respects_limit(): void
    {
        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            Message::create([
                'id' => "msg-{$i}",
                'content' => "Message {$i}",
                'channel_id' => $channel->id,
                'author_id' => $human->id,
                'timestamp' => now()->addSeconds($i),
            ]);
        }

        $messages = $this->loader->load($channel->id, $agent, 3);

        $this->assertCount(3, $messages);
    }

    public function test_returns_empty_for_no_messages(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create();

        $messages = $this->loader->load($channel->id, $agent);

        $this->assertCount(0, $messages);
    }

    /**
     * Custom assertion for string contains (PHPUnit 11 compatible).
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
