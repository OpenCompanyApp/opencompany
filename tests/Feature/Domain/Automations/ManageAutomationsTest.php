<?php

namespace Tests\Feature\Domain\Automations;

use App\Domain\Automations\Application\ExecuteScriptAutomation;
use App\Domain\Automations\Application\ManageAutomations;
use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManageAutomationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_preview_and_bulk_run_automations(): void
    {
        Bus::fake();

        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $this->actingAs($creator);

        $manager = app(ManageAutomations::class);
        $automation = $manager->create([
            'name' => 'Daily summary',
            'agentId' => $agent->id,
            'prompt' => 'Summarize yesterday.',
            'cronExpression' => '0 9 * * *',
            'timezone' => 'UTC',
            'createdById' => $creator->id,
        ]);

        $preview = $manager->previewSchedule('0 9 * * *', 'UTC');
        $result = $manager->bulkRun([$automation->id, 'missing']);

        $this->assertCount(5, $preview);
        $this->assertSame(1, $result['triggered']);
        $this->assertSame(1, $result['skipped']);
        Bus::assertDispatched(RunAutomationJob::class);
    }

    public function test_string_boolean_inputs_are_normalized_like_laravel_requests(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $this->actingAs($creator);

        $manager = app(ManageAutomations::class);
        $automation = $manager->create([
            'name' => 'No history',
            'agentId' => $agent->id,
            'prompt' => 'Summarize yesterday.',
            'cronExpression' => '0 9 * * *',
            'keepHistory' => 'false',
            'createdById' => $creator->id,
        ]);

        $this->assertFalse($automation->keep_history);

        $updated = $manager->update($automation->id, [
            'isActive' => 'false',
            'keepHistory' => 'true',
        ]);

        $this->assertFalse($updated->is_active);
        $this->assertTrue($updated->keep_history);
    }

    public function test_script_automation_execution_records_task_output_and_message(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $automation = Automation::create([
            'id' => 'script-automation',
            'workspace_id' => $this->workspace->id,
            'name' => 'Script summary',
            'execution_type' => 'script',
            'agent_id' => $agent->id,
            'script' => 'console.log("hello from script"); return "ok";',
            'script_runtime' => 'quickjs-v1',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $creator->id,
            'is_active' => true,
            'keep_history' => true,
        ]);

        app(ExecuteScriptAutomation::class)->handle($automation);

        $task = Task::where('source', Task::SOURCE_AUTOMATION)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        $this->assertSame(Task::STATUS_COMPLETED, $task->status);
        $this->assertSame('hello from script', $task->result['output']);
        $this->assertSame('ok', $task->result['return_value']);
        $this->assertTrue(Message::where('author_id', $agent->id)
            ->where('content', 'hello from script')
            ->where('source', 'automation')
            ->exists());

        $automation->refresh();
        $this->assertSame(1, $automation->run_count);
        $this->assertSame('quickjs-v1', $automation->last_result['script_runtime']);
        $this->assertArrayHasKey('execution_id', $automation->last_result);
        $this->assertArrayHasKey('effects', $automation->last_result);
    }

    public function test_script_creation_compiles_and_pins_the_runtime(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $this->actingAs($creator);

        $automation = app(ManageAutomations::class)->create([
            'name' => 'JavaScript report',
            'executionType' => 'script',
            'agentId' => $agent->id,
            'script' => 'return { ok: true };',
            'cronExpression' => '0 9 * * *',
            'timezone' => 'UTC',
            'createdById' => $creator->id,
        ]);

        $this->assertSame('quickjs-v1', $automation->script_runtime);
        $this->assertTrue($automation->is_active);
    }

    public function test_invalid_javascript_is_rejected_before_persistence(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $this->actingAs($creator);

        try {
            app(ManageAutomations::class)->create([
                'name' => 'Broken script',
                'executionType' => 'script',
                'agentId' => $agent->id,
                'script' => 'const broken = ;',
                'cronExpression' => '0 9 * * *',
                'createdById' => $creator->id,
            ]);
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('syntax_error', $exception->errors()['script'][0]);
        }

        $this->assertFalse(Automation::where('name', 'Broken script')->exists());
    }

    public function test_unpinned_legacy_script_refuses_execution_without_creating_a_task(): void
    {
        $creator = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $automation = Automation::create([
            'id' => 'legacy-script',
            'workspace_id' => $this->workspace->id,
            'name' => 'Legacy script',
            'execution_type' => 'script',
            'agent_id' => $agent->id,
            'script' => 'return true',
            'script_runtime' => null,
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $creator->id,
            'is_active' => false,
            'keep_history' => true,
        ]);

        app(ExecuteScriptAutomation::class)->handle($automation);

        $this->assertFalse(Task::where('context->automation_id', $automation->id)->exists());
        $automation->refresh();
        $this->assertSame(1, $automation->consecutive_failures);
        $this->assertFalse($automation->last_result['retryable']);
        $this->assertSame('quickjs-v1', $automation->last_result['required_runtime']);
    }
}
