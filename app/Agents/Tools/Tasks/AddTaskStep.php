<?php

namespace App\Agents\Tools\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddTaskStep implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Add a new step to your currently running task. The step will be automatically started.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'taskId' => $schema
                ->string()
                ->description('The UUID of the task (provided when the task was created for you).')
                ->required(),
            'name' => $schema
                ->string()
                ->description('Step name/description. Must not be empty.')
                ->required(),
            'stepType' => $schema
                ->string()
                ->description('Step type: action, decision, approval, sub_task, or message. Default: action.'),
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

            $name = trim($request['name'] ?? '');
            if ($name === '') {
                return 'Error: name must not be empty.';
            }

            $stepType = $request['stepType'] ?? 'action';

            $step = $task->addStep($name, $stepType);
            $step->start();

            return "Step added: '{$name}' (id: {$step->id})";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }
}
