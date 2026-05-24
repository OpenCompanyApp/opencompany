<?php

namespace Tests\Feature\Domain\Work;

use App\Domain\Work\Application\ManageTasks;
use App\Jobs\ExecuteAgentTaskJob;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ManageTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_task_lifecycle_and_steps_are_workspace_scoped(): void
    {
        $requester = User::factory()->create(['type' => 'human']);
        $tasks = app(ManageTasks::class);

        $task = $tasks->create([
            'title' => 'Prepare briefing',
            'description' => 'Collect notes',
            'requesterId' => $requester->id,
        ]);

        $started = $tasks->start($task->id);
        $step = $tasks->addStep($task->id, ['description' => 'Collected sources']);
        $completed = $tasks->transition($task->id, 'complete', ['response' => 'Done']);

        $this->assertSame(Task::STATUS_ACTIVE, $started->status);
        $this->assertSame('Collected sources', $step->description);
        $this->assertSame(Task::STATUS_COMPLETED, $completed->status);
        $this->assertSame(['response' => 'Done'], $completed->result);
    }

    public function test_start_dispatches_agent_runtime_for_assigned_tasks(): void
    {
        Bus::fake();

        $requester = User::factory()->create(['type' => 'human']);
        $agent = User::factory()->agent()->create();
        $task = Task::create([
            'id' => 'assigned-task',
            'workspace_id' => $this->workspace->id,
            'title' => 'Assigned work',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_NORMAL,
            'requester_id' => $requester->id,
            'agent_id' => $agent->id,
        ]);

        app(ManageTasks::class)->start($task->id);

        Bus::assertDispatched(ExecuteAgentTaskJob::class);
    }

    public function test_step_mutations_do_not_cross_workspace_boundaries(): void
    {
        $otherWorkspace = Workspace::create([
            'name' => 'Other Workspace',
            'slug' => 'other',
        ]);
        $requester = User::factory()->create(['type' => 'human']);
        $task = Task::create([
            'id' => 'other-task',
            'workspace_id' => $otherWorkspace->id,
            'title' => 'Other work',
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_NORMAL,
            'requester_id' => $requester->id,
        ]);
        $step = $task->addStep('Other workspace step');

        $this->expectException(ModelNotFoundException::class);

        app(ManageTasks::class)->updateStep($task->id, $step->id, [
            'description' => 'Should not update',
        ]);
    }
}
