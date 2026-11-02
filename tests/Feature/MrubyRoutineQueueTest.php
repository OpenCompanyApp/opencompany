<?php

namespace Tests\Feature;

use App\Domain\Routines\Application\ExecuteScriptRoutine;
use App\Jobs\RunRoutineJob;
use App\Jobs\RunScriptRoutineJob;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Services\ScriptAdmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/** Protects dispatch-time source identity and at-most-once whole-script entry. */
class MrubyRoutineQueueTest extends TestCase
{
    use RefreshDatabase;

    private function routine(): Routine
    {
        $source = '"queue result"';

        return Routine::create([
            'id' => 'ruby-queue', 'workspace_id' => $this->workspace->id,
            'name' => 'Ruby queue test', 'kind' => 'script',
            'agent_id' => User::factory()->agent()->create(['status' => 'idle'])->id,
            'created_by_id' => User::factory()->create()->id,
            'script' => $source, ...app(ScriptAdmission::class)->admit($source),
            'cron_expression' => '0 9 * * *', 'timezone' => 'UTC',
            'is_active' => true, 'keep_history' => true,
        ]);
    }

    public function test_serialized_script_job_cannot_execute_a_newer_admitted_source(): void
    {
        $routine = $this->routine();
        $payload = serialize(new RunScriptRoutineJob($routine));
        $source = '"edited after enqueue"';
        $routine->update(['script' => $source, ...app(ScriptAdmission::class)->admit($source)]);
        $executor = Mockery::mock(ExecuteScriptRoutine::class);
        $executor->shouldNotReceive('handle');

        unserialize($payload)->handle($executor);

        $this->assertTrue($routine->fresh()->is_active);
        $this->assertSame(0, Task::count());
    }

    public function test_outer_queue_envelope_preserves_receipt_and_uses_script_retry_limits(): void
    {
        $routine = $this->routine();
        $job = new RunRoutineJob($routine);
        $payload = serialize($job);
        // Overlap releases are bounded by retryUntil() (tries=0); the single
        // semantic attempt is enforced by the script AgentRunRecord instead.
        $this->assertSame(0, $job->tries);
        $this->assertSame(60, $job->timeout);
        $routine->update(['is_active' => false]);

        unserialize($payload)->handle();

        $this->assertSame(0, Task::count());
    }

    public function test_old_payload_without_source_receipt_fails_closed(): void
    {
        $job = new RunScriptRoutineJob($this->routine());
        (new \ReflectionProperty($job, 'sourceReceipt'))->setValue($job, null);
        $executor = Mockery::mock(ExecuteScriptRoutine::class);
        $executor->shouldNotReceive('handle');

        $job->handle($executor);

        $this->assertSame(0, Task::count());
    }

    public function test_duplicate_queue_delivery_reuses_task_claim_without_reexecuting(): void
    {
        $routine = $this->routine();
        $payload = serialize(new RunRoutineJob($routine));

        unserialize($payload)->handle();
        unserialize($payload)->handle();

        $this->assertSame(1, Task::where('source', Task::SOURCE_ROUTINE)->count());
        $this->assertSame(1, $routine->fresh()->run_count);
        $this->assertSame('queue result', Task::firstOrFail()->result['return_value']);
    }
}
