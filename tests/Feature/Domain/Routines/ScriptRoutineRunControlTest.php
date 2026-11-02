<?php

namespace Tests\Feature\Domain\Routines;

use App\Domain\Routines\Application\ExecuteScriptRoutine;
use App\Models\AgentRunRecord;
use App\Models\Message;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Services\ScriptAdmission;
use App\Services\ScriptRoutineInvocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Protects the merge of mruby script routines with the durable run
 * control plane: one AgentRunRecord per dispatch key, exactly one semantic
 * attempt (a Ruby program may already have written externally), and the
 * same agent-availability rules as prompt routines.
 */
class ScriptRoutineRunControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! is_executable((string) config('code.engine_binary'))) {
            $this->markTestSkipped('Requires the pinned Ruby engine (RUBY_ENGINE_BINARY).');
        }
        Event::fake();
    }

    private function routine(string $agentStatus = 'idle', string $source = 'puts("hello"); {done: true}'): Routine
    {
        return Routine::create([
            'id' => 'script-run-control', 'workspace_id' => $this->workspace->id,
            'name' => 'Run control', 'kind' => 'script',
            'agent_id' => User::factory()->agent()->create(['status' => $agentStatus])->id,
            'created_by_id' => User::factory()->create()->id,
            'script' => $source, ...app(ScriptAdmission::class)->admit($source),
            'cron_expression' => '0 9 * * *', 'timezone' => 'UTC', 'is_active' => true, 'keep_history' => true,
        ]);
    }

    public function test_successful_run_is_recorded_once_with_correlated_output(): void
    {
        $routine = $this->routine();
        $receipt = ScriptRoutineInvocation::capture($routine);

        app(ExecuteScriptRoutine::class)->handle($routine, 'dispatch-1', 'job-1', $receipt);

        $run = AgentRunRecord::query()->sole();
        $this->assertSame(AgentRunRecord::KIND_ROUTINE_SCRIPT, $run->kind);
        $this->assertSame(AgentRunRecord::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, $run->max_attempts);
        $this->assertSame(Task::STATUS_COMPLETED, $run->task->status);
        $this->assertSame(1, Message::query()->where('agent_run_id', $run->id)->count());
        $this->assertSame(1, $routine->fresh()->run_count);
    }

    // A worker crash after the program started leaves the run "running".
    // Redelivery must not re-run code that may already have written.
    public function test_redelivered_dispatch_after_start_fails_as_ambiguous_without_rerunning(): void
    {
        $routine = $this->routine(source: 'puts("side effect"); 1');
        $receipt = ScriptRoutineInvocation::capture($routine);
        app(ExecuteScriptRoutine::class)->handle($routine, 'dispatch-2', 'job-1', $receipt);
        $run = AgentRunRecord::query()->sole();
        // Simulate the crash window: attempt began, terminal state never written.
        $run->forceFill(['status' => AgentRunRecord::STATUS_RUNNING, 'finished_at' => null])->save();
        $run->task->forceFill(['status' => Task::STATUS_ACTIVE, 'completed_at' => null])->save();
        $outputBefore = Message::query()->count();

        app(ExecuteScriptRoutine::class)->handle($routine->fresh(), 'dispatch-2', 'job-2', $receipt);

        $run->refresh();
        $this->assertSame(AgentRunRecord::STATUS_FAILED, $run->status);
        $this->assertSame(1, $run->attempt_count, 'no second semantic attempt');
        $this->assertSame(Task::STATUS_FAILED, $run->task->fresh()->status);
        $this->assertSame($outputBefore, Message::query()->count(), 'the program did not run again');
    }

    public function test_offline_agent_parks_the_run_instead_of_executing(): void
    {
        $routine = $this->routine(agentStatus: 'offline');

        app(ExecuteScriptRoutine::class)->handle($routine, 'dispatch-3', 'job-1', ScriptRoutineInvocation::capture($routine));

        $run = AgentRunRecord::query()->sole();
        $this->assertSame(0, $run->attempt_count);
        $this->assertSame(Task::STATUS_PAUSED, $run->task->status);
    }

    // Source edited after scheduling: the queued receipt no longer matches.
    public function test_stale_source_receipt_never_executes(): void
    {
        $routine = $this->routine();
        $receipt = ScriptRoutineInvocation::capture($routine);
        $source = '"edited"';
        $routine->update(['script' => $source, ...app(ScriptAdmission::class)->admit($source)]);

        app(ExecuteScriptRoutine::class)->handle($routine->fresh(), 'dispatch-4', 'job-1', $receipt);

        $this->assertSame(0, AgentRunRecord::query()->count());
        $this->assertSame(0, Task::query()->count());
    }
}
