<?php

namespace Tests\Feature\Domain\Routines;

use App\Domain\Routines\Application\ExecuteScriptRoutine;
use App\Domain\Routines\Application\ManageRoutines;
use App\Jobs\RunRoutineJob;
use App\Models\Message;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Services\ScriptAdmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ManageRoutinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_preview_and_bulk_run_routines(): void
    {
        Bus::fake();

        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $this->actingAs($creator);

        $manager = app(ManageRoutines::class);
        $routine = $manager->create([
            'name' => 'Daily summary',
            'agentId' => $agent->id,
            'instructions' => 'Summarize yesterday.',
            'cronExpression' => '0 9 * * *',
            'timezone' => 'UTC',
            'createdById' => $creator->id,
        ]);

        $preview = $manager->previewSchedule('0 9 * * *', 'UTC');
        $result = $manager->bulkRun([$routine->id, 'missing']);

        $this->assertCount(5, $preview);
        $this->assertSame(1, $result['triggered']);
        $this->assertSame(1, $result['skipped']);
        Bus::assertDispatched(RunRoutineJob::class);
    }

    public function test_string_boolean_inputs_are_normalized_like_laravel_requests(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $this->actingAs($creator);

        $manager = app(ManageRoutines::class);
        $routine = $manager->create([
            'name' => 'No history',
            'agentId' => $agent->id,
            'instructions' => 'Summarize yesterday.',
            'cronExpression' => '0 9 * * *',
            'keepHistory' => 'false',
            'createdById' => $creator->id,
        ]);

        $this->assertFalse($routine->keep_history);

        $updated = $manager->update($routine->id, [
            'isActive' => 'false',
            'keepHistory' => 'true',
        ]);

        $this->assertFalse($updated->is_active);
        $this->assertTrue($updated->keep_history);
    }

    public function test_routine_script_execution_records_task_output_and_message(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create(['status' => 'idle']);
        $source = 'puts "hello from script"; "ok"';
        $routine = Routine::create([
            'id' => 'script-routine',
            'workspace_id' => $this->workspace->id,
            'name' => 'Script summary',
            'kind' => 'script',
            'agent_id' => $agent->id,
            'script' => $source,
            ...app(ScriptAdmission::class)->admit($source),
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $creator->id,
            'is_active' => true,
            'keep_history' => true,
        ]);

        // Exercise real compilation, immutable admission and subprocess
        // execution. A fabricated sandbox result would miss runtime drift.
        app(ExecuteScriptRoutine::class)->handle($routine);

        $task = Task::where('source', Task::SOURCE_ROUTINE)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        $this->assertSame(Task::STATUS_COMPLETED, $task->status);
        $this->assertSame('hello from script', $task->result['output']);
        $this->assertSame('ok', $task->result['return_value']);
        $this->assertTrue(Message::where('author_id', $agent->id)
            ->where('content', 'hello from script')
            ->where('source', 'routine')
            ->exists());

        $routine->refresh();
        $this->assertSame(1, $routine->run_count);
    }
}
