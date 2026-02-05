<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Events\MessageSent;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgentRespondJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_responds_to_message(): void
    {
        OpenCompanyAgent::fake(['Hello! I can help you with that.']);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $channel = Channel::factory()->create(['type' => 'dm']);

        $userMessage = Message::create([
            'id' => 'msg-1',
            'content' => 'Can you help me with testing?',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        $job = new AgentRespondJob($userMessage, $agent, $channel->id);
        $job->handle();

        // Verify agent created a response message
        $agentMessages = Message::where('author_id', $agent->id)->get();
        $this->assertCount(1, $agentMessages);
        $this->assertEquals('Hello! I can help you with that.', $agentMessages->first()->content);
        $this->assertEquals($channel->id, $agentMessages->first()->channel_id);

        // Verify broadcast
        Event::assertDispatched(MessageSent::class);

        // Verify agent status reset
        $agent->refresh();
        $this->assertEquals('idle', $agent->status);
    }

    public function test_agent_sends_error_message_on_failure(): void
    {
        OpenCompanyAgent::fake(function () {
            throw new \RuntimeException('Provider unavailable');
        });
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $channel = Channel::factory()->create(['type' => 'dm']);

        $userMessage = Message::create([
            'id' => 'msg-1',
            'content' => 'Hello',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        $job = new AgentRespondJob($userMessage, $agent, $channel->id);
        $job->handle();

        // Verify error message was sent
        $agentMessages = Message::where('author_id', $agent->id)->get();
        $this->assertCount(1, $agentMessages);
        $this->assertStringContainsString('error', strtolower($agentMessages->first()->content));

        // Agent status should be reset
        $agent->refresh();
        $this->assertEquals('idle', $agent->status);
    }

    public function test_agent_status_set_to_working_during_execution(): void
    {
        $statusDuringExecution = null;

        OpenCompanyAgent::fake(function () use (&$statusDuringExecution) {
            // Capture agent status mid-execution
            $statusDuringExecution = User::where('type', 'agent')->first()->status;
            return 'Done processing.';
        });
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $channel = Channel::factory()->create(['type' => 'dm']);

        $userMessage = Message::create([
            'id' => 'msg-1',
            'content' => 'Test',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        $job = new AgentRespondJob($userMessage, $agent, $channel->id);
        $job->handle();

        $this->assertEquals('working', $statusDuringExecution);

        // Status reset after completion
        $agent->refresh();
        $this->assertEquals('idle', $agent->status);
    }
}
