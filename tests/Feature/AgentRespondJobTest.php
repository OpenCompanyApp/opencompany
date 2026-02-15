<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Events\MessageSent;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
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

    // ── Delegation Callback Tests ─────────────────────────────────────

    public function test_delegation_task_picked_up_from_pending(): void
    {
        OpenCompanyAgent::fake(['Delegation result here.']);
        Event::fake([MessageSent::class]);

        $agentA = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $agentB = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $dmChannel = Channel::factory()->create(['type' => 'dm']);

        // Create pending delegation subtask
        $subtask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Delegated work',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'source' => Task::SOURCE_AGENT_DELEGATION,
            'agent_id' => $agentB->id,
            'requester_id' => $agentA->id,
            'channel_id' => $dmChannel->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $triggerMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Please do this work',
            'channel_id' => $dmChannel->id,
            'author_id' => $agentA->id,
            'timestamp' => now(),
        ]);

        $job = new AgentRespondJob($triggerMessage, $agentB, $dmChannel->id);
        $job->handle();

        $subtask->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $subtask->status);
        $this->assertNotNull($subtask->started_at);

        // No additional task should have been created
        $this->assertEquals(1, Task::where('agent_id', $agentB->id)->count());
    }

    public function test_delegation_callback_posts_result_and_removes_awaiting(): void
    {
        OpenCompanyAgent::fake(['Subtask completed successfully.']);
        Event::fake();

        $agentA = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $agentB = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $parentChannel = Channel::factory()->create();
        $dmChannel = Channel::factory()->create(['type' => 'dm']);

        // Setup DM for callback
        DirectMessage::create([
            'id' => Str::uuid()->toString(),
            'user1_id' => $agentB->id,
            'user2_id' => $agentA->id,
            'channel_id' => $dmChannel->id,
        ]);

        $parentTask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Parent work',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agentA->id,
            'requester_id' => $agentA->id,
            'channel_id' => $parentChannel->id,
            'started_at' => now(),
            'workspace_id' => $this->workspace->id,
        ]);

        $subtask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Delegated subtask',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'source' => Task::SOURCE_AGENT_DELEGATION,
            'agent_id' => $agentB->id,
            'requester_id' => $agentA->id,
            'channel_id' => $dmChannel->id,
            'parent_task_id' => $parentTask->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $agentA->addAwaitingDelegation($subtask->id);

        $triggerMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Please handle this',
            'channel_id' => $dmChannel->id,
            'author_id' => $agentA->id,
            'timestamp' => now(),
        ]);

        // Use Bus::fake to prevent the callback job from running
        Bus::fake([AgentRespondJob::class]);

        $job = new AgentRespondJob($triggerMessage, $agentB, $dmChannel->id);
        $job->handle();

        // Verify delegation result posted to DM (by child agent)
        $dmResults = Message::where('source', 'delegation_result')
            ->where('channel_id', $dmChannel->id)
            ->where('author_id', $agentB->id)
            ->get();
        $this->assertGreaterThanOrEqual(1, $dmResults->count());

        // Verify parent agent's awaiting list was cleared
        $agentA->refresh();
        $this->assertFalse($agentA->isAwaitingDelegation());

        // Verify callback job dispatched for parent agent with taskId
        Bus::assertDispatched(AgentRespondJob::class, function ($job) use ($agentA, $parentTask) {
            $agentRef = new \ReflectionProperty($job, 'agent');
            $taskIdRef = new \ReflectionProperty($job, 'taskId');
            return $agentRef->getValue($job)->id === $agentA->id
                && $taskIdRef->getValue($job) === $parentTask->id;
        });
    }

    public function test_delegation_failure_sends_error_callback(): void
    {
        OpenCompanyAgent::fake(function () {
            throw new \RuntimeException('LLM provider error');
        });
        Event::fake();

        $agentA = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $agentB = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);
        $parentChannel = Channel::factory()->create();
        $dmChannel = Channel::factory()->create(['type' => 'dm']);

        DirectMessage::create([
            'id' => Str::uuid()->toString(),
            'user1_id' => $agentB->id,
            'user2_id' => $agentA->id,
            'channel_id' => $dmChannel->id,
        ]);

        $parentTask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Parent work',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agentA->id,
            'requester_id' => $agentA->id,
            'channel_id' => $parentChannel->id,
            'started_at' => now(),
            'workspace_id' => $this->workspace->id,
        ]);

        $subtask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Failing subtask',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'source' => Task::SOURCE_AGENT_DELEGATION,
            'agent_id' => $agentB->id,
            'requester_id' => $agentA->id,
            'channel_id' => $dmChannel->id,
            'parent_task_id' => $parentTask->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $agentA->addAwaitingDelegation($subtask->id);

        $triggerMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Please do this',
            'channel_id' => $dmChannel->id,
            'author_id' => $agentA->id,
            'timestamp' => now(),
        ]);

        Bus::fake([AgentRespondJob::class]);

        $job = new AgentRespondJob($triggerMessage, $agentB, $dmChannel->id);
        $job->handle();

        // Subtask should be marked as failed
        $subtask->refresh();
        $this->assertEquals(Task::STATUS_FAILED, $subtask->status);

        // Error callback should have been sent
        $errorCallbacks = Message::where('source', 'delegation_result')
            ->where('author_id', $agentB->id)
            ->get();
        $this->assertGreaterThanOrEqual(1, $errorCallbacks->count());

        // Callback job dispatched for parent with taskId
        Bus::assertDispatched(AgentRespondJob::class, function ($job) use ($agentA, $parentTask) {
            $agentRef = new \ReflectionProperty($job, 'agent');
            $taskIdRef = new \ReflectionProperty($job, 'taskId');
            return $agentRef->getValue($job)->id === $agentA->id
                && $taskIdRef->getValue($job) === $parentTask->id;
        });
    }

    public function test_status_awaiting_delegation_when_pending(): void
    {
        OpenCompanyAgent::fake(['Done.']);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
            'awaiting_delegation_ids' => ['task-pending-123'],
        ]);
        $channel = Channel::factory()->create(['type' => 'dm']);

        $userMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Hello',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        $job = new AgentRespondJob($userMessage, $agent, $channel->id);
        $job->handle();

        $agent->refresh();
        $this->assertEquals('awaiting_delegation', $agent->status);

        // Response should be held — no message delivered to user
        $agentMessages = Message::where('author_id', $agent->id)->get();
        $this->assertCount(0, $agentMessages);

        // Task should still be active (not completed)
        $task = Task::where('agent_id', $agent->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals(Task::STATUS_ACTIVE, $task->status);
    }

    public function test_task_resumed_via_task_id(): void
    {
        OpenCompanyAgent::fake(['Here are the delegation results summarized.']);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'awaiting_delegation',
        ]);
        $channel = Channel::factory()->create(['type' => 'dm']);

        // Create an existing task that was waiting for delegation results
        $existingTask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Original user request',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $human->id,
            'channel_id' => $channel->id,
            'started_at' => now(),
            'workspace_id' => $this->workspace->id,
        ]);

        $resultMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => '[Result from Echo] Here is a joke.',
            'channel_id' => $channel->id,
            'author_id' => $agent->id,
            'timestamp' => now(),
            'source' => 'delegation_result',
        ]);

        // Dispatch with taskId to resume the existing task
        $job = new AgentRespondJob($resultMessage, $agent, $channel->id, $existingTask->id);
        $job->handle();

        // Verify the existing task was completed (not a new task created)
        $existingTask->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $existingTask->status);

        // Should NOT have created a second task
        $agentTasks = Task::where('agent_id', $agent->id)->get();
        $this->assertCount(1, $agentTasks);

        // Agent should have sent a response message
        $agentMessages = Message::where('author_id', $agent->id)
            ->where('source', null)
            ->get();
        $this->assertCount(1, $agentMessages);
        $this->assertStringContainsString('delegation results', $agentMessages->first()->content);
    }

    public function test_status_idle_when_no_delegations(): void
    {
        OpenCompanyAgent::fake(['Done.']);
        Event::fake([MessageSent::class]);

        $human = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
            'awaiting_delegation_ids' => null,
        ]);
        $channel = Channel::factory()->create(['type' => 'dm']);

        $userMessage = Message::create([
            'id' => Str::uuid()->toString(),
            'content' => 'Hello',
            'channel_id' => $channel->id,
            'author_id' => $human->id,
            'timestamp' => now(),
        ]);

        $job = new AgentRespondJob($userMessage, $agent, $channel->id);
        $job->handle();

        $agent->refresh();
        $this->assertEquals('idle', $agent->status);
    }
}
