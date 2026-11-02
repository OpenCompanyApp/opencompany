<?php

namespace Tests\Feature\AgentSafety;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\Chat\SendChannelMessage;
use App\Agents\Tools\Tasks\SetTaskStatus;
use App\Agents\Tools\ToolRegistry;
use App\Models\AgentRunRecord;
use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\ApprovalExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Regressions found by the live GLM Code Mode evals and the P0 follow-ups.
 */
class RunTaskAndExternalSendGuardsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->agent = User::factory()->agent()->create(['status' => 'idle']);
    }

    private function task(): Task
    {
        return Task::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $this->workspace->id, 'title' => 'chat',
            'type' => Task::TYPE_CUSTOM, 'status' => Task::STATUS_ACTIVE, 'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT, 'agent_id' => $this->agent->id, 'requester_id' => $this->agent->id, 'started_at' => now(),
        ]);
    }

    // Eval finding: GLM "completed" the chat task it was answering via
    // set_task_status; the next checkpoint treated that as an external
    // cancellation and the reply was dropped. The run's own task must stay
    // active; the reported result is recorded instead.
    public function test_agent_cannot_terminate_the_task_its_current_run_executes(): void
    {
        $task = $this->task();
        $run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_CHAT);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        $result = (new SetTaskStatus($this->agent))->handle(new Request(['taskId' => $task->id, 'status' => 'completed', 'result' => 'total is 42']));

        $this->assertStringContainsString('closed automatically after your final reply', $result);
        $this->assertSame(Task::STATUS_ACTIVE, $task->fresh()->status);
        $this->assertSame('total is 42', $task->fresh()->context['agent_reported_result']);
        app()->forgetInstance(ActiveAgentRun::class);

        // Outside a run (e.g. a delegated task finished later) it still works.
        (new SetTaskStatus($this->agent))->handle(new Request(['taskId' => $task->id, 'status' => 'completed']));
        $this->assertSame(Task::STATUS_COMPLETED, $task->fresh()->status);
    }

    // Outbound messages to a bridged (Telegram/Slack) channel that is not the
    // current conversation need approval; the approved replay delivers once.
    public function test_external_channel_send_requires_approval_and_replays_once_approved(): void
    {
        $external = Channel::factory()->create(['workspace_id' => $this->workspace->id, 'type' => 'external', 'name' => 'customers']);
        $current = Channel::factory()->create(['workspace_id' => $this->workspace->id, 'type' => 'dm']);
        app(ToolRegistry::class)->setChannelContext($current->id);

        $tool = app(SendChannelMessage::class, ['agent' => $this->agent]);
        $reply = $tool->handle(new Request(['channelId' => $external->id, 'content' => 'Hello customers']));

        $this->assertStringContainsString('requires human approval', $reply);
        $this->assertSame(0, Message::where('channel_id', $external->id)->count());
        $approval = ApprovalRequest::query()->sole();
        $this->assertSame('send_channel_message', $approval->tool_execution_context['tool_slug']);

        // Replying inside the conversation being answered is not gated.
        app(ToolRegistry::class)->setChannelContext($external->id);
        $tool->handle(new Request(['channelId' => $external->id, 'content' => 'In-thread reply']));
        $this->assertSame(1, Message::where('channel_id', $external->id)->count());
        app(ToolRegistry::class)->setChannelContext($current->id);

        $admin = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $this->workspace->id, 'user_id' => $admin->id, 'role' => 'admin']);
        $this->assertTrue(app(ApprovalExecutionService::class)->resolve($approval, 'approved', $admin));

        $this->assertSame(1, Message::where('channel_id', $external->id)->where('content', 'Hello customers')->count());
        $this->assertSame(1, ApprovalRequest::count(), 'the approved replay must not create a second approval');
    }
}
