<?php

namespace Tests\Feature\Tools;

use App\Agents\OpenCompanyAgent;
use App\Agents\Tools\Agents\ContactAgent;
use App\Jobs\AgentRespondJob;
use App\Models\AgentPermission;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\AgentCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ContactAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

private function makeTool(User $agent): ContactAgent
    {
        return new ContactAgent(
            $agent,
            app(AgentPermissionService::class),
            app(AgentCommunicationService::class),
        );
    }

    public function test_notify_sends_dm_message(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'notify',
            'agentId' => $target->id,
            'message' => 'Hey, just a heads up!',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Notification sent', $result);

        // Assert DM channel was created
        $dmChannel = Channel::where('type', 'dm')->first();
        $this->assertNotNull($dmChannel);

        // Assert message exists in the DM channel
        $message = Message::where('channel_id', $dmChannel->id)
            ->where('author_id', $caller->id)
            ->first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('Hey, just a heads up!', $message->content);

        // Notify creates a standalone task for the target agent
        $notifyTask = Task::where('source', Task::SOURCE_AGENT_NOTIFY)->first();
        $this->assertNotNull($notifyTask);
        $this->assertEquals($target->id, $notifyTask->agent_id);
        $this->assertNull($notifyTask->parent_task_id);
    }

    public function test_notify_works_for_offline_target(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'offline']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'notify',
            'agentId' => $target->id,
            'message' => 'FYI: deployment finished.',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Notification sent', $result);
    }

    public function test_blocks_self_contact(): void
    {
        $caller = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'notify',
            'agentId' => $caller->id,
            'message' => 'Talking to myself',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('cannot contact yourself', $result);
    }

    public function test_error_for_nonexistent_agent(): void
    {
        $caller = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'notify',
            'agentId' => Str::uuid()->toString(),
            'message' => 'Hello ghost',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Agent not found', $result);
    }

    public function test_error_for_invalid_action(): void
    {
        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'invalid',
            'agentId' => $target->id,
            'message' => 'This should fail',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Invalid action', $result);
    }

    public function test_ask_error_for_offline_target(): void
    {
        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'offline']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'ask',
            'agentId' => $target->id,
            'message' => 'Are you there?',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('currently offline', $result);
    }

    public function test_ask_wakes_sleeping_agent(): void
    {
        OpenCompanyAgent::fake(['I am awake now!']);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create([
            'status' => 'sleeping',
            'sleeping_until' => now()->addHours(2),
            'sleeping_reason' => 'Taking a nap',
        ]);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'ask',
            'agentId' => $target->id,
            'message' => 'Wake up, I need help!',
        ]);

        $tool->handle($request);

        $target->refresh();
        $this->assertNull($target->sleeping_until);
        $this->assertEquals('idle', $target->status);
    }

    public function test_ask_dispatches_async_job(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'ask',
            'agentId' => $target->id,
            'message' => 'What is the answer?',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Question sent to', $result);

        // Assert DM channel was created with messages
        $dmChannel = Channel::where('type', 'dm')->first();
        $this->assertNotNull($dmChannel);

        $messageCount = Message::where('channel_id', $dmChannel->id)->count();
        $this->assertGreaterThanOrEqual(2, $messageCount);

        // Assert job was dispatched
        Bus::assertDispatched(AgentRespondJob::class);
    }

    public function test_ask_creates_task_with_parent(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);
        $channel = Channel::factory()->create();

        // Create an active parent task for the caller
        $parentTask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Parent task',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $caller->id,
            'requester_id' => $caller->id,
            'channel_id' => $channel->id,
            'started_at' => now(),
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'ask',
            'agentId' => $target->id,
            'message' => 'What is the meaning of life?',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Question sent to', $result);

        // Assert ask task was created, linked to parent, pending async execution
        $askTask = Task::where('source', Task::SOURCE_AGENT_ASK)
            ->where('agent_id', $target->id)
            ->first();
        $this->assertNotNull($askTask);
        $this->assertEquals(Task::STATUS_PENDING, $askTask->status);
        $this->assertEquals($parentTask->id, $askTask->parent_task_id);
        $this->assertEquals($caller->id, $askTask->requester_id);

        // Assert caller is tracking the delegation
        $caller->refresh();
        $this->assertContains($askTask->id, $caller->awaiting_delegation_ids);
    }

    public function test_notify_creates_standalone_task(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'notify',
            'agentId' => $target->id,
            'message' => 'FYI: deployment complete',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Notification sent', $result);

        // Notify creates a standalone task (no parent) for the target
        $task = Task::where('source', Task::SOURCE_AGENT_NOTIFY)->first();
        $this->assertNotNull($task);
        $this->assertEquals($target->id, $task->agent_id);
        $this->assertEquals($caller->id, $task->requester_id);
        $this->assertNull($task->parent_task_id);

        Bus::assertDispatched(AgentRespondJob::class);
    }

    public function test_delegate_error_for_offline_target(): void
    {
        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'offline']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'delegate',
            'agentId' => $target->id,
            'message' => 'Please handle this',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('currently offline', $result);
    }

    public function test_delegate_creates_subtask(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);
        $channel = Channel::factory()->create();

        $callerTask = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Caller task',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $caller->id,
            'requester_id' => $caller->id,
            'channel_id' => $channel->id,
            'started_at' => now(),
            'workspace_id' => $this->workspace->id,
        ]);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'delegate',
            'agentId' => $target->id,
            'message' => 'Please write a report on Q4 earnings',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Delegated to', $result);

        $subtask = Task::where('source', Task::SOURCE_AGENT_DELEGATION)
            ->where('agent_id', $target->id)
            ->first();

        $this->assertNotNull($subtask);
        $this->assertEquals(Task::SOURCE_AGENT_DELEGATION, $subtask->source);
        $this->assertEquals($callerTask->id, $subtask->parent_task_id);
        $this->assertEquals($caller->id, $subtask->requester_id);
        $this->assertEquals(Task::STATUS_PENDING, $subtask->status);
    }

    public function test_delegate_adds_awaiting_delegation(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'delegate',
            'agentId' => $target->id,
            'message' => 'Handle customer support ticket #123',
        ]);

        $tool->handle($request);

        $subtask = Task::where('source', Task::SOURCE_AGENT_DELEGATION)
            ->where('agent_id', $target->id)
            ->first();
        $this->assertNotNull($subtask);

        $caller->refresh();
        $this->assertIsArray($caller->awaiting_delegation_ids);
        $this->assertContains($subtask->id, $caller->awaiting_delegation_ids);
    }

    public function test_delegate_dispatches_job(): void
    {
        Bus::fake([AgentRespondJob::class]);

        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'delegate',
            'agentId' => $target->id,
            'message' => 'Analyze the dataset',
        ]);

        $tool->handle($request);

        Bus::assertDispatched(AgentRespondJob::class);
    }

    public function test_checks_permissions_deny_creates_access_request(): void
    {
        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $caller->id,
            'scope_type' => 'agent',
            'scope_key' => $target->id,
            'permission' => 'deny',
            'requires_approval' => false,
        ]);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'notify',
            'agentId' => $target->id,
            'message' => 'You should not receive this',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('requires approval', $result);
        $this->assertStringContainsString('access request has been created', $result);

        // Verify an access-type approval was created
        $this->assertDatabaseHas('approval_requests', [
            'type' => 'access',
            'requester_id' => $caller->id,
            'status' => 'pending',
        ]);
    }

    public function test_requires_approval_creates_approval_request(): void
    {
        $caller = User::factory()->agent()->create(['status' => 'idle']);
        $target = User::factory()->agent()->create(['status' => 'idle']);

        AgentPermission::create([
            'id' => Str::uuid()->toString(),
            'agent_id' => $caller->id,
            'scope_type' => 'agent',
            'scope_key' => $target->id,
            'permission' => 'allow',
            'requires_approval' => true,
        ]);

        $tool = $this->makeTool($caller);
        $request = new Request([
            'action' => 'delegate',
            'agentId' => $target->id,
            'message' => 'Do something important',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('requires approval', $result);
        $this->assertStringContainsString($target->name, $result);

        $approval = \App\Models\ApprovalRequest::where('requester_id', $caller->id)->first();
        $this->assertNotNull($approval);
        $this->assertEquals('pending', $approval->status);
        $this->assertEquals('contact_agent', $approval->tool_execution_context['tool_slug']);
    }
}
