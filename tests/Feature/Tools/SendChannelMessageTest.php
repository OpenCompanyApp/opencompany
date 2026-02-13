<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Chat\SendChannelMessage;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class SendChannelMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_message_to_channel(): void
    {
        Event::fake();

        $agent = User::factory()->create(['type' => 'agent']);
        $channel = Channel::factory()->create(['name' => 'general']);

        $tool = new SendChannelMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['channelId' => $channel->id, 'content' => 'Hello from agent!']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Message sent to', $result);
        $this->assertStringContainsString('general', $result);
        $this->assertMatchesRegularExpression('/msg:[a-f0-9]{6}/', $result);

        $message = Message::where('author_id', $agent->id)->first();
        $this->assertNotNull($message);
        $this->assertEquals('Hello from agent!', $message->content);
        $this->assertEquals($channel->id, $message->channel_id);
    }

    public function test_returns_error_for_missing_channel(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $tool = new SendChannelMessage($agent, app(AgentPermissionService::class));
        $request = new Request(['channelId' => 'nonexistent', 'content' => 'Hello']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('not found', $result);
    }

    public function test_has_correct_description(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $tool = new SendChannelMessage($agent, app(AgentPermissionService::class));

        $this->assertStringContainsString('Send a message', $tool->description());
    }
}
