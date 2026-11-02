<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Runtime\AgentRunBuilder;
use App\Agents\Runtime\AgentRunOptions;
use App\Agents\Runtime\AgentRuntimeEvent;
use App\Agents\Runtime\AmbiguousAgentToolExecution;
use App\Agents\Runtime\Subagents\GenericSubagent;
use App\Agents\Tools\System\RunGuardedTool;
use App\Domain\AgentRuntime\Application\DetectDeliveredChatResponse;
use App\Domain\Routines\Application\ResolveRoutineRun;
use App\Domain\Work\Application\ManageTasks;
use App\Http\Controllers\Api\SettingController;
use App\Jobs\AgentRespondJob;
use App\Jobs\ExecuteAgentTaskJob;
use App\Jobs\RunRoutineJob;
use App\Models\AgentRunRecord;
use App\Models\AgentToolExecution;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Chat\DirectMessageResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\TestCase;

class AgentRunControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_agent_job_types_share_one_per_agent_overlap_lock(): void
    {
        $agent = User::factory()->agent()->create();
        $human = User::factory()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);
        $message = Message::factory()->create([
            'author_id' => $human->id,
            'channel_id' => $channel->id,
        ]);
        $task = Task::createPending($message, $agent, $channel->id);
        $routine = new Routine(['agent_id' => $agent->id]);

        $chatJob = new AgentRespondJob($message, $agent, $channel->id, $task->id);
        $taskJob = new ExecuteAgentTaskJob($task);
        $routineJob = new RunRoutineJob($routine);

        $keys = collect([$chatJob, $taskJob, $routineJob])
            ->map(fn ($job) => $job->middleware()[0]->getLockKey($job));

        $this->assertCount(1, $keys->unique());
        $this->assertStringContainsString($agent->id, $keys->first());
        $this->assertTrue($chatJob->retryUntil()->isAfter(now()->addHours(23)));
    }

    public function test_cancelled_queued_chat_job_cannot_run_or_deliver(): void
    {
        OpenCompanyAgent::fake(['This response must never be delivered.']);

        $human = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);
        $message = Message::factory()->create([
            'author_id' => $human->id,
            'channel_id' => $channel->id,
        ]);
        $task = Task::createPending($message, $agent, $channel->id);
        $task->cancel();

        (new AgentRespondJob($message, $agent, $channel->id, $task->id))->handle();

        $this->assertSame(Task::STATUS_CANCELLED, $task->fresh()->status);
        $this->assertSame(AgentRunRecord::STATUS_CANCELLED, $task->agentRun->fresh()->status);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_delivery_retry_detection_uses_exact_run_correlation(): void
    {
        $human = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);
        $trigger = Message::factory()->create([
            'author_id' => $human->id,
            'channel_id' => $channel->id,
        ]);
        $task = Task::createPending($trigger, $agent, $channel->id);
        $run = $task->agentRun;

        Message::factory()->create([
            'author_id' => $agent->id,
            'channel_id' => $channel->id,
            'timestamp' => now()->addSecond(),
        ]);

        $detector = app(DetectDeliveredChatResponse::class);
        $this->assertFalse($detector->handle($task, $run));

        Message::factory()->create([
            'author_id' => $agent->id,
            'channel_id' => $channel->id,
            'agent_run_id' => $run->id,
        ]);

        $this->assertTrue($detector->handle($task, $run));
    }

    public function test_task_creation_rejects_cross_workspace_agent_ids(): void
    {
        $otherWorkspace = Workspace::create(['name' => 'Other', 'slug' => 'other']);
        $foreignAgent = User::factory()->agent()->create(['workspace_id' => $otherWorkspace->id]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('assigned agent');

        app(ManageTasks::class)->create([
            'title' => 'Cross-tenant task',
            'agentId' => $foreignAgent->id,
        ]);
    }

    public function test_subagent_rejects_channel_outside_target_workspace_before_provider_call(): void
    {
        $otherWorkspace = Workspace::create(['name' => 'Other', 'slug' => 'other-subagent']);
        $foreignAgent = User::factory()->agent()->create([
            'workspace_id' => $otherWorkspace->id,
            'status' => 'idle',
        ]);
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);

        // mruby's peer-owned child tasks require an active parent in the
        // target workspace; a foreign agent fails closed before any provider
        // call or child task creation.
        $this->expectException(\RuntimeException::class);

        try {
            GenericSubagent::for($foreignAgent, $channel->id)->prompt('Do not execute this.');
        } finally {
            $this->assertSame(0, Task::query()->where('agent_id', $foreignAgent->id)->count());
        }
    }

    public function test_same_dm_participants_get_distinct_workspace_conversations(): void
    {
        $firstHuman = User::factory()->create();
        $secondHuman = User::factory()->create();
        foreach ([$firstHuman, $secondHuman] as $human) {
            WorkspaceMember::create([
                'workspace_id' => $this->workspace->id,
                'user_id' => $human->id,
                'role' => 'member',
            ]);
        }
        $resolver = app(DirectMessageResolver::class);

        $first = $resolver->getOrCreate($firstHuman->id, $secondHuman->id);

        $otherWorkspace = Workspace::create(['name' => 'Other', 'slug' => 'other']);
        foreach ([$firstHuman, $secondHuman] as $human) {
            WorkspaceMember::create([
                'workspace_id' => $otherWorkspace->id,
                'user_id' => $human->id,
                'role' => 'member',
            ]);
        }
        app()->instance('currentWorkspace', $otherWorkspace);
        $second = $resolver->getOrCreate($firstHuman->id, $secondHuman->id);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($this->workspace->id, $first->workspace_id);
        $this->assertSame($otherWorkspace->id, $second->workspace_id);
        $this->assertDatabaseCount('direct_messages', 2);
    }

    public function test_routine_dispatch_key_reuses_one_task_and_run(): void
    {
        $creator = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);
        $routine = Routine::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Daily report',
            'kind' => 'agent',
            'agent_id' => $agent->id,
            'instructions' => 'Summarize today.',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'channel_id' => $channel->id,
            'keep_history' => true,
            'created_by_id' => $creator->id,
        ]);

        $dispatchKey = (string) Str::uuid();
        $resolver = app(ResolveRoutineRun::class);
        $first = $resolver->handle($routine, $dispatchKey, AgentRunRecord::KIND_ROUTINE_AGENT, 2);
        $second = $resolver->handle($routine, $dispatchKey, AgentRunRecord::KIND_ROUTINE_AGENT, 2);

        $this->assertSame($first->task->id, $second->task->id);
        $this->assertSame($first->run->id, $second->run->id);
        $this->assertDatabaseCount('agent_runs', 1);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_due_routine_creates_durable_run_before_physical_dispatch(): void
    {
        Bus::fake([RunRoutineJob::class]);
        $routine = $this->routineForControlPlane();
        $routine->update(['next_run_at' => now()->subMinute()]);

        $this->artisan('routines:tick')->assertSuccessful();

        $run = AgentRunRecord::query()->where('workspace_id', $this->workspace->id)->firstOrFail();
        $this->assertSame(AgentRunRecord::STATUS_QUEUED, $run->status);
        $this->assertSame($routine->id, $run->metadata['routine_id']);
        $this->assertSame(Task::STATUS_PENDING, $run->task->status);
        $this->assertTrue($routine->fresh()->next_run_at->isFuture());
        Bus::assertDispatched(RunRoutineJob::class);
    }

    public function test_stale_queued_routine_dispatch_is_recovered_not_failed(): void
    {
        Bus::fake([RunRoutineJob::class]);
        config()->set('agent_runtime.stale_queued_after_seconds', 60);
        $routine = $this->routineForControlPlane();
        $dispatchKey = (string) Str::uuid();
        $context = app(ResolveRoutineRun::class)->handle(
            $routine,
            $dispatchKey,
            AgentRunRecord::KIND_ROUTINE_AGENT,
            2,
        );
        $context->run->update(['heartbeat_at' => now()->subMinutes(5)]);

        $this->artisan('agent-runs:reconcile')->assertSuccessful();

        $run = $context->run->fresh();
        $this->assertSame(AgentRunRecord::STATUS_QUEUED, $run->status);
        $this->assertSame(Task::STATUS_PENDING, $run->task->status);
        $this->assertNotNull($run->metadata['recovered_dispatch_at'] ?? null);
        Bus::assertDispatched(RunRoutineJob::class, function (RunRoutineJob $job) use ($dispatchKey): bool {
            $property = new \ReflectionProperty($job, 'dispatchKey');

            return $property->getValue($job) === $dispatchKey;
        });
    }

    public function test_stale_running_run_is_reconciled_to_failed_task(): void
    {
        config()->set('agent_runtime.stale_running_after_seconds', 60);

        $human = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);
        $message = Message::factory()->create([
            'author_id' => $human->id,
            'channel_id' => $channel->id,
        ]);
        $task = Task::createPending($message, $agent, $channel->id)->start();
        $run = $task->agentRun;
        $run->update([
            'status' => AgentRunRecord::STATUS_RUNNING,
            'heartbeat_at' => now()->subMinutes(5),
        ]);

        $this->artisan('agent-runs:reconcile')->assertSuccessful();

        $this->assertSame(Task::STATUS_FAILED, $task->fresh()->status);
        $this->assertSame(AgentRunRecord::STATUS_FAILED, $run->fresh()->status);
    }

    public function test_terminal_run_repairs_nonterminal_task_after_interrupted_transition(): void
    {
        $run = $this->pendingRun();
        $run->task->start();
        $run->markFailed('Worker failed between terminal writes.');

        $this->artisan('agent-runs:reconcile')->assertSuccessful();

        $task = $run->task->fresh();
        $this->assertSame(AgentRunRecord::STATUS_FAILED, $run->fresh()->status);
        $this->assertSame(Task::STATUS_FAILED, $task->status);
        $this->assertSame('Worker failed between terminal writes.', $task->result['error']);
    }

    public function test_failed_run_dismissal_is_workspace_scoped_and_retains_history(): void
    {
        $first = $this->failedRunForWorkspace($this->workspace);
        $otherWorkspace = Workspace::create(['name' => 'Other', 'slug' => 'other']);
        $other = $this->failedRunForWorkspace($otherWorkspace);

        app()->instance('currentWorkspace', $this->workspace);
        $request = Request::create('/api/settings/danger-action', 'POST', ['action' => 'flush_failed_jobs']);
        app(SettingController::class)->dangerAction($request);

        $this->assertNotNull($first->fresh()->metadata['operator_dismissed_at'] ?? null);
        $this->assertNull($other->fresh()->metadata['operator_dismissed_at'] ?? null);
        $this->assertDatabaseHas('agent_runs', ['id' => $first->id]);
        $this->assertDatabaseHas('agent_runs', ['id' => $other->id]);
    }

    public function test_runtime_event_keeps_occurrence_time_when_serialized_later(): void
    {
        CarbonImmutable::setTestNow('2026-08-09 10:00:00');
        $event = new AgentRuntimeEvent('run.started');
        CarbonImmutable::setTestNow('2026-08-09 10:05:00');

        $this->assertSame('2026-08-09T10:00:00.000000Z', $event->toArray()['at']);

        CarbonImmutable::setTestNow();
    }

    // Run options cap the agent's step budget for this run only.
    public function test_agent_run_options_apply_max_steps(): void
    {
        $agent = User::factory()->agent()->create([
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
        ]);
        $run = app(AgentRunBuilder::class)->build(
            $agent,
            'channel-id',
            options: new AgentRunOptions(maxTurns: 4),
        );

        $this->assertSame(4, $run->agent()->maxSteps());
    }

    public function test_completed_tool_call_is_replayed_without_executing_side_effect_twice(): void
    {
        $run = $this->pendingRun();
        $calls = 0;
        $inner = \Mockery::mock(Tool::class);
        $inner->shouldReceive('name')->andReturn('send_external_message');
        $inner->shouldReceive('handle')->once()->andReturnUsing(function () use (&$calls): string {
            $calls++;

            return 'external-id-123';
        });
        $guarded = new RunGuardedTool($inner);

        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));
        $first = $guarded->handle(new ToolRequest(['body' => 'hello', 'to' => 'customer']));

        // A queue retry receives a fresh in-memory run handle, while the
        // durable sequence-one ledger record survives the worker boundary.
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));
        $second = $guarded->handle(new ToolRequest(['to' => 'customer', 'body' => 'hello']));

        $this->assertSame('external-id-123', $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
        $this->assertDatabaseHas('agent_tool_executions', [
            'agent_run_id' => $run->id,
            'surface' => 'provider',
            'sequence' => 1,
            'status' => AgentToolExecution::STATUS_COMPLETED,
        ]);
    }

    public function test_ambiguous_tool_call_is_not_repeated_automatically(): void
    {
        $run = $this->pendingRun();
        $calls = 0;
        $inner = \Mockery::mock(Tool::class);
        $inner->shouldReceive('name')->andReturn('create_remote_record');
        $inner->shouldReceive('handle')->once()->andReturnUsing(function () use (&$calls): never {
            $calls++;
            throw new \RuntimeException('Connection dropped after request was sent.');
        });
        $guarded = new RunGuardedTool($inner);
        $arguments = new ToolRequest(['name' => 'Potentially created']);

        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));
        try {
            $guarded->handle($arguments);
            $this->fail('The first ambiguous tool call should throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Connection dropped after request was sent.', $exception->getMessage());
        }

        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        try {
            $guarded->handle($arguments);
            $this->fail('The retry should stop at the ambiguous ledger record.');
        } catch (AmbiguousAgentToolExecution $exception) {
            $this->assertStringContainsString('was not repeated', $exception->getMessage());
        }

        $this->assertSame(1, $calls);
        $this->assertDatabaseHas('agent_tool_executions', [
            'agent_run_id' => $run->id,
            'surface' => 'provider',
            'sequence' => 1,
            'status' => AgentToolExecution::STATUS_AMBIGUOUS,
        ]);
    }

    public function test_identical_tool_calls_in_one_attempt_keep_distinct_sequences(): void
    {
        $run = $this->pendingRun();
        $calls = 0;
        $inner = \Mockery::mock(Tool::class);
        $inner->shouldReceive('name')->andReturn('refresh_remote_state');
        $inner->shouldReceive('handle')->twice()->andReturnUsing(function () use (&$calls): string {
            return 'result-'.++$calls;
        });
        $guarded = new RunGuardedTool($inner);
        app()->instance(ActiveAgentRun::class, new ActiveAgentRun($run->id));

        $first = $guarded->handle(new ToolRequest(['record' => 'same-record']));
        $second = $guarded->handle(new ToolRequest(['record' => 'same-record']));

        $this->assertSame('result-1', $first);
        $this->assertSame('result-2', $second);
        $this->assertSame(2, $calls);
        $this->assertDatabaseHas('agent_tool_executions', ['agent_run_id' => $run->id, 'sequence' => 1]);
        $this->assertDatabaseHas('agent_tool_executions', ['agent_run_id' => $run->id, 'sequence' => 2]);
    }

    public function test_provider_tool_sequence_continues_after_persisted_retry_checkpoints(): void
    {
        $run = $this->pendingRun();
        $run->task->addStep('Previously completed call', metadata: [
            'checkpointed' => true,
            'tool' => 'prior_tool',
        ])->complete();

        $active = new ActiveAgentRun($run->id);

        $this->assertSame(2, $active->nextProviderToolSequence());
    }

    private function failedRunForWorkspace(Workspace $workspace): AgentRunRecord
    {
        $agent = User::factory()->agent()->create(['workspace_id' => $workspace->id]);
        $requester = User::factory()->create();
        $task = Task::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'title' => 'Failed task',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_FAILED,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_MANUAL,
            'agent_id' => $agent->id,
            'requester_id' => $requester->id,
            'completed_at' => now(),
        ]);
        $run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_ASSIGNED_TASK, 3);
        $run->markFailed('Provider failed.');

        return $run;
    }

    private function pendingRun(): AgentRunRecord
    {
        $human = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);
        $message = Message::factory()->create([
            'author_id' => $human->id,
            'channel_id' => $channel->id,
        ]);

        return Task::createPending($message, $agent, $channel->id)->agentRun;
    }

    private function routineForControlPlane(): Routine
    {
        $creator = User::factory()->create();
        $agent = User::factory()->agent()->create();
        $channel = Channel::factory()->dm()->create(['workspace_id' => $this->workspace->id]);

        return Routine::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'name' => 'Recoverable schedule',
            'kind' => 'agent',
            'agent_id' => $agent->id,
            'instructions' => 'Produce the scheduled report.',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'channel_id' => $channel->id,
            'keep_history' => true,
            'created_by_id' => $creator->id,
            'next_run_at' => now()->addDay(),
        ]);
    }
}
