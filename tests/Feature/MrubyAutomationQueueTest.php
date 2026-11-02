<?php

namespace Tests\Feature;

use App\Domain\Automations\Application\ExecuteScriptAutomation;
use App\Jobs\RunAutomationJob;
use App\Jobs\RunScriptAutomationJob;
use App\Models\Automation;
use App\Models\Task;
use App\Models\User;
use App\Services\ScriptAdmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/** Protects dispatch-time source identity and at-most-once whole-script entry. */
class MrubyAutomationQueueTest extends TestCase
{
    use RefreshDatabase;

    private function automation(): Automation
    {
        $source = '"queue result"';

        return Automation::create([
            'id' => 'ruby-queue', 'workspace_id' => $this->workspace->id,
            'name' => 'Ruby queue test', 'execution_type' => 'script',
            'agent_id' => User::factory()->agent()->create()->id,
            'created_by_id' => User::factory()->create()->id,
            'script' => $source, ...app(ScriptAdmission::class)->admit($source),
            'cron_expression' => '0 9 * * *', 'timezone' => 'UTC',
            'is_active' => true, 'keep_history' => true,
        ]);
    }

    public function test_serialized_script_job_cannot_execute_a_newer_admitted_source(): void
    {
        $automation = $this->automation();
        $payload = serialize(new RunScriptAutomationJob($automation));
        $source = '"edited after enqueue"';
        $automation->update(['script' => $source, ...app(ScriptAdmission::class)->admit($source)]);
        $executor = Mockery::mock(ExecuteScriptAutomation::class);
        $executor->shouldNotReceive('handle');

        unserialize($payload)->handle($executor);

        $this->assertTrue($automation->fresh()->is_active);
        $this->assertSame(0, Task::count());
    }

    public function test_outer_queue_envelope_preserves_receipt_and_uses_script_retry_limits(): void
    {
        $automation = $this->automation();
        $job = new RunAutomationJob($automation);
        $payload = serialize($job);
        $this->assertSame(1, $job->tries);
        $this->assertSame(60, $job->timeout);
        $automation->update(['is_active' => false]);

        unserialize($payload)->handle();

        $this->assertSame(0, Task::count());
    }

    public function test_old_payload_without_source_receipt_fails_closed(): void
    {
        $job = new RunScriptAutomationJob($this->automation());
        (new \ReflectionProperty($job, 'sourceReceipt'))->setValue($job, null);
        $executor = Mockery::mock(ExecuteScriptAutomation::class);
        $executor->shouldNotReceive('handle');

        $job->handle($executor);

        $this->assertSame(0, Task::count());
    }

    public function test_duplicate_queue_delivery_reuses_task_claim_without_reexecuting(): void
    {
        $automation = $this->automation();
        $payload = serialize(new RunAutomationJob($automation));

        unserialize($payload)->handle();
        unserialize($payload)->handle();

        $this->assertSame(1, Task::where('source', Task::SOURCE_AUTOMATION)->count());
        $this->assertSame(1, $automation->fresh()->run_count);
        $this->assertSame('queue result', Task::firstOrFail()->result['return_value']);
    }
}
