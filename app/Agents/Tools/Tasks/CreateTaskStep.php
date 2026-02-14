<?php

namespace App\Agents\Tools\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateTaskStep implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Log a progress step on a task you are working on. Use this to track your work and show progress to the team.';
    }

    public function handle(Request $request): string
    {
        try {
            $taskId = $request['taskId'];
            $description = $request['description'];
            $stepType = $request['stepType'] ?? 'action';

            $task = Task::find($taskId);
            if (!$task) {
                return "Error: Task '{$taskId}' not found.";
            }

            if ($task->agent_id !== $this->agent->id) {
                return "Error: This task is not assigned to you.";
            }

            $step = $task->addStep($description, $stepType);
            $step->start();

            return "Task step logged: '{$description}' (type: {$stepType})";
        } catch (\Throwable $e) {
            return "Error creating task step: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'taskId' => $schema
                ->string()
                ->description('The UUID of the task to add a step to.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('Description of what was done in this step.')
                ->required(),
            'stepType' => $schema
                ->string()
                ->description('Type of step: action, decision, approval, sub_task, or message. Default: action.'),
        ];
    }
}
