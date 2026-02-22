<?php

namespace App\Agents\Tools\Tasks;

use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateTaskStep implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Update the description of a step on your currently running task.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'taskId' => $schema
                ->string()
                ->description('The UUID of the task (provided when the task was created for you).')
                ->required(),
            'stepId' => $schema
                ->string()
                ->description('Step UUID to update.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('New step description. Must not be empty.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        try {
            $taskId = $request['taskId'];

            $task = Task::find($taskId);
            if (!$task) {
                return "Error: Task '{$taskId}' not found.";
            }

            if ($task->agent_id !== $this->agent->id) {
                return "Error: This task is not assigned to you.";
            }

            $stepId = $request['stepId'] ?? '';
            if ($stepId === '') {
                return 'Error: stepId is required.';
            }

            $description = trim($request['description'] ?? '');
            if ($description === '') {
                return 'Error: description must not be empty.';
            }

            $step = TaskStep::where('task_id', $task->id)
                ->where('id', $stepId)
                ->first();

            if (!$step) {
                return "Error: Step '{$stepId}' not found on this task.";
            }

            $step->update(['description' => $description]);

            return "Step updated: '{$description}'";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }
}
