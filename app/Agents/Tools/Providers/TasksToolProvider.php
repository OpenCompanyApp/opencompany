<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Tasks\AddTaskStep;
use App\Agents\Tools\Tasks\CreateTaskStep;
use App\Agents\Tools\Tasks\SetTaskStatus;
use App\Agents\Tools\Tasks\UpdateTask;
use App\Agents\Tools\Tasks\UpdateTaskStep;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers task-progress tools.
 *
 * These tools are the agent-facing surface for updating the task created around
 * an agent run, including step logging and final status changes.
 */
class TasksToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'tasks';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'update, add_step, update_step, set_status',
            'description' => 'Work progress tracking',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:list-checks';
    }

    public function tools(): array
    {
        return [
            'update_task' => [
                'class' => UpdateTask::class,
                'type' => 'write',
                'name' => 'Update Task',
                'description' => 'Update a task\'s title or description.',
                'icon' => 'ph:list-checks',
            ],
            'add_task_step' => [
                'class' => AddTaskStep::class,
                'type' => 'write',
                'name' => 'Add Task Step',
                'description' => 'Add a progress step to a task.',
                'icon' => 'ph:list-checks',
            ],
            'update_task_step' => [
                'class' => UpdateTaskStep::class,
                'type' => 'write',
                'name' => 'Update Task Step',
                'description' => 'Update the description of a task step.',
                'icon' => 'ph:list-checks',
            ],
            'set_task_status' => [
                'class' => SetTaskStatus::class,
                'type' => 'write',
                'name' => 'Set Task Status',
                'description' => 'Set a task as completed or failed.',
                'icon' => 'ph:list-checks',
            ],
            'create_task_step' => [
                'class' => CreateTaskStep::class,
                'type' => 'write',
                'name' => 'Create Task Step',
                'description' => 'Log a progress step on a task you are working on.',
                'icon' => 'ph:list-checks',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // Task tools use the agent as the actor and resolve the current task
        // from tool arguments or runtime context inside each concrete class.
        return new $class($agent);
    }
}
