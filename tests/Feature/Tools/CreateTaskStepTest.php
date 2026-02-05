<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\CreateTaskStep;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CreateTaskStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_task_step(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $requester = User::factory()->create(['type' => 'human']);

        $task = Task::create([
            'id' => 'task-1',
            'title' => 'Test task',
            'type' => 'custom',
            'status' => 'active',
            'priority' => 'normal',
            'agent_id' => $agent->id,
            'requester_id' => $requester->id,
        ]);

        $tool = new CreateTaskStep($agent);
        $request = new Request([
            'taskId' => $task->id,
            'description' => 'Analyzing requirements',
            'stepType' => 'action',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Task step logged', $result);
        $this->assertStringContainsString('Analyzing requirements', $result);

        $step = TaskStep::where('task_id', $task->id)->first();
        $this->assertNotNull($step);
        $this->assertEquals('Analyzing requirements', $step->description);
        $this->assertEquals('action', $step->step_type);
        $this->assertEquals('in_progress', $step->status); // start() sets it
    }

    public function test_rejects_task_not_assigned_to_agent(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $otherAgent = User::factory()->create(['type' => 'agent']);
        $requester = User::factory()->create(['type' => 'human']);

        $task = Task::create([
            'id' => 'task-1',
            'title' => 'Other agent task',
            'type' => 'custom',
            'status' => 'active',
            'priority' => 'normal',
            'agent_id' => $otherAgent->id,
            'requester_id' => $requester->id,
        ]);

        $tool = new CreateTaskStep($agent);
        $request = new Request([
            'taskId' => $task->id,
            'description' => 'Trying to log on wrong task',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('not assigned to you', $result);
        $this->assertEquals(0, TaskStep::count());
    }

    public function test_returns_error_for_missing_task(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);

        $tool = new CreateTaskStep($agent);
        $request = new Request([
            'taskId' => 'nonexistent',
            'description' => 'Step description',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('not found', $result);
    }

    public function test_defaults_step_type_to_action(): void
    {
        $agent = User::factory()->create(['type' => 'agent']);
        $requester = User::factory()->create(['type' => 'human']);

        $task = Task::create([
            'id' => 'task-1',
            'title' => 'Test task',
            'type' => 'custom',
            'status' => 'active',
            'priority' => 'normal',
            'agent_id' => $agent->id,
            'requester_id' => $requester->id,
        ]);

        $tool = new CreateTaskStep($agent);
        $request = new Request([
            'taskId' => $task->id,
            'description' => 'Default type step',
        ]);

        $tool->handle($request);

        $step = TaskStep::where('task_id', $task->id)->first();
        $this->assertEquals('action', $step->step_type);
    }
}
