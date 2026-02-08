<?php

namespace Tests\Feature;

use App\Agents\OpenCompanyAgent;
use App\Events\TaskUpdated;
use App\Jobs\ExecuteAgentTaskJob;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExecuteAgentTaskJobTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'type' => 'agent',
            'brain' => 'anthropic:claude-sonnet-4-5-20250929',
            'status' => 'idle',
        ]);

        $this->requester = User::factory()->create(['type' => 'human']);
    }

    public function test_task_lifecycle_on_success(): void
    {
        OpenCompanyAgent::fake(['Task analysis complete. The report shows positive trends.']);
        Event::fake([TaskUpdated::class]);

        $task = Task::create([
            'id' => 'task-1',
            'title' => 'Analyze Q4 metrics',
            'description' => 'Review Q4 performance data',
            'type' => 'analysis',
            'status' => 'pending',
            'priority' => 'normal',
            'agent_id' => $this->agent->id,
            'requester_id' => $this->requester->id,
        ]);

        $job = new ExecuteAgentTaskJob($task);
        $job->handle();

        $task->refresh();

        $this->assertEquals('completed', $task->status);
        $this->assertNotNull($task->started_at);
        $this->assertNotNull($task->completed_at);
        $this->assertNotNull($task->result);
        $this->assertStringContainsString('positive trends', $task->result['response']);

        // Verify steps were created
        $steps = $task->steps;
        $this->assertGreaterThanOrEqual(2, $steps->count());

        // Verify agent status reset
        $this->agent->refresh();
        $this->assertEquals('idle', $this->agent->status);

        // Verify broadcasts
        Event::assertDispatched(TaskUpdated::class, function ($event) {
            return $event->action === 'started';
        });
        Event::assertDispatched(TaskUpdated::class, function ($event) {
            return $event->action === 'completed';
        });
    }

    public function test_task_fails_without_agent(): void
    {
        Event::fake([TaskUpdated::class]);

        $task = Task::create([
            'id' => 'task-2',
            'title' => 'Unassigned task',
            'type' => 'custom',
            'status' => 'pending',
            'priority' => 'normal',
            'agent_id' => null,
            'requester_id' => $this->requester->id,
        ]);

        $job = new ExecuteAgentTaskJob($task);
        $job->handle();

        $task->refresh();
        $this->assertEquals('failed', $task->status);

        Event::assertDispatched(TaskUpdated::class, function ($event) {
            return $event->action === 'failed';
        });
    }

    public function test_task_fails_gracefully_on_error(): void
    {
        OpenCompanyAgent::fake(function () {
            throw new \RuntimeException('API connection failed');
        });
        Event::fake([TaskUpdated::class]);

        $task = Task::create([
            'id' => 'task-3',
            'title' => 'Failing task',
            'type' => 'custom',
            'status' => 'pending',
            'priority' => 'normal',
            'agent_id' => $this->agent->id,
            'requester_id' => $this->requester->id,
        ]);

        $job = new ExecuteAgentTaskJob($task);
        $job->handle();

        $task->refresh();
        $this->assertEquals('failed', $task->status);
        $this->assertNotNull($task->result);
        $this->assertArrayHasKey('error', $task->result);

        // Agent status should be reset
        $this->agent->refresh();
        $this->assertEquals('idle', $this->agent->status);

        Event::assertDispatched(TaskUpdated::class, function ($event) {
            return $event->action === 'failed';
        });
    }

    public function test_task_start_endpoint_dispatches_job(): void
    {
        OpenCompanyAgent::fake(['Done.']);
        Event::fake([TaskUpdated::class]);

        $taskId = \Illuminate\Support\Str::uuid()->toString();
        $task = Task::create([
            'id' => $taskId,
            'title' => 'API triggered task',
            'type' => 'custom',
            'status' => 'pending',
            'priority' => 'normal',
            'agent_id' => $this->agent->id,
            'requester_id' => $this->requester->id,
        ]);

        // Since queue is sync in test, this will execute immediately
        $response = $this->postJson("/api/tasks/{$taskId}/start");

        $response->assertOk();

        $task->refresh();
        // With sync queue, task should be completed by now
        $this->assertContains($task->status, ['active', 'completed']);
    }
}
