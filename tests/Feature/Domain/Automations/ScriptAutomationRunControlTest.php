<?php

namespace Tests\Feature\Domain\Automations;

use App\Domain\Automations\Application\ExecuteScriptAutomation;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\ScriptAdmission;
use App\Services\ScriptAutomationInvocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Protects the merge of mruby script automations with the durable run
 * control plane: one AgentRunRecord per dispatch key, exactly one semantic
 * attempt (a Ruby program may already have written externally), and the
 * same agent-availability rules as prompt automations.
 */
class ScriptAutomationRunControlTest extends TestCase
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

    private function automation(string $agentStatus = 'idle', string $source = 'puts("hello"); {done: true}'): Automation
    {
        return Automation::create([
            'id' => 'script-run-control', 'workspace_id' => $this->workspace->id,
            'name' => 'Run control', 'execution_type' => 'script',
            'agent_id' => User::factory()->agent()->create(['status' => $agentStatus])->id,
            'created_by_id' => User::factory()->create()->id,
            'script' => $source, ...app(ScriptAdmission::class)->admit($source),
            'cron_expression' => '0 9 * * *', 'timezone' => 'UTC', 'is_active' => true, 'keep_history' => true,
        ]);
    }

    public function test_successful_run_is_recorded_once_with_correlated_output(): void
    {
        $automation = $this->automation();
        $receipt = ScriptAutomationInvocation::capture($automation);

        app(ExecuteScriptAutomation::class)->handle($automation, 'dispatch-1', 'job-1', $receipt);

        $run = AgentRunRecord::query()->sole();
        $this->assertSame(AgentRunRecord::KIND_SCRIPT_AUTOMATION, $run->kind);
        $this->assertSame(AgentRunRecord::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, $run->max_attempts);
        $this->assertSame(Task::STATUS_COMPLETED, $run->task->status);
        $this->assertSame(1, Message::query()->where('agent_run_id', $run->id)->count());
        $this->assertSame(1, $automation->fresh()->run_count);
    }

    // A worker crash after the program started leaves the run "running".
    // Redelivery must not re-run code that may already have written.
    public function test_redelivered_dispatch_after_start_fails_as_ambiguous_without_rerunning(): void
    {
        $automation = $this->automation(source: 'puts("side effect"); 1');
        $receipt = ScriptAutomationInvocation::capture($automation);
        app(ExecuteScriptAutomation::class)->handle($automation, 'dispatch-2', 'job-1', $receipt);
        $run = AgentRunRecord::query()->sole();
        // Simulate the crash window: attempt began, terminal state never written.
        $run->forceFill(['status' => AgentRunRecord::STATUS_RUNNING, 'finished_at' => null])->save();
        $run->task->forceFill(['status' => Task::STATUS_ACTIVE, 'completed_at' => null])->save();
        $outputBefore = Message::query()->count();

        app(ExecuteScriptAutomation::class)->handle($automation->fresh(), 'dispatch-2', 'job-2', $receipt);

        $run->refresh();
        $this->assertSame(AgentRunRecord::STATUS_FAILED, $run->status);
        $this->assertSame(1, $run->attempt_count, 'no second semantic attempt');
        $this->assertSame(Task::STATUS_FAILED, $run->task->fresh()->status);
        $this->assertSame($outputBefore, Message::query()->count(), 'the program did not run again');
    }

    public function test_offline_agent_parks_the_run_instead_of_executing(): void
    {
        $automation = $this->automation(agentStatus: 'offline');

        app(ExecuteScriptAutomation::class)->handle($automation, 'dispatch-3', 'job-1', ScriptAutomationInvocation::capture($automation));

        $run = AgentRunRecord::query()->sole();
        $this->assertSame(0, $run->attempt_count);
        $this->assertSame(Task::STATUS_PAUSED, $run->task->status);
    }

    // Source edited after scheduling: the queued receipt no longer matches.
    public function test_stale_source_receipt_never_executes(): void
    {
        $automation = $this->automation();
        $receipt = ScriptAutomationInvocation::capture($automation);
        $source = '"edited"';
        $automation->update(['script' => $source, ...app(ScriptAdmission::class)->admit($source)]);

        app(ExecuteScriptAutomation::class)->handle($automation->fresh(), 'dispatch-4', 'job-1', $receipt);

        $this->assertSame(0, AgentRunRecord::query()->count());
        $this->assertSame(0, Task::query()->count());
    }
}
