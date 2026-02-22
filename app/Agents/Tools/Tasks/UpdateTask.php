<?php

namespace App\Agents\Tools\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateTask implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Update the title and/or description of your currently running task. Always set a clear title and description at the start of a task.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'taskId' => $schema
                ->string()
                ->description('The UUID of the task (provided when the task was created for you).')
                ->required(),
            'title' => $schema
                ->string()
                ->description('New task title. Short, descriptive summary of the work.'),
            'description' => $schema
                ->string()
                ->description('New task description.'),
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

            $title = trim($request['title'] ?? '');
            $description = trim($request['description'] ?? '');

            if ($title === '' && $description === '') {
                return 'Error: provide at least a title or description.';
            }

            $data = [];
            if ($title !== '') {
                $data['title'] = $title;
            }
            if ($description !== '') {
                $data['description'] = $description;
            }

            $task->update($data);

            $parts = [];
            if (isset($data['title'])) {
                $parts[] = "title: '{$data['title']}'";
            }
            if (isset($data['description'])) {
                $parts[] = "description updated";
            }

            return 'Task updated — ' . implode(', ', $parts);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }
}
