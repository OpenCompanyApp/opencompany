<?php

namespace Tests\Feature\Domain\Automations;

use App\Domain\Automations\Application\ExecuteScriptAutomation;
use App\Domain\Automations\Application\ManageAutomations;
use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\LuaResult;
use App\Services\LuaSandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
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
            'script' => 'print("hello")',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'created_by_id' => $creator->id,
            'is_active' => true,
            'keep_history' => true,
        ]);

        $sandbox = Mockery::mock(LuaSandboxService::class);
        $sandbox->shouldReceive('execute')
            ->once()
            ->andReturn(new LuaResult('hello from script', null, 'ok', 12.3, 1024));
        app()->instance(LuaSandboxService::class, $sandbox);

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
    }
}
