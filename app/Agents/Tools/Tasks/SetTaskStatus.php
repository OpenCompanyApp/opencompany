<?php

namespace App\Agents\Tools\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SetTaskStatus implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Set the final status of your currently running task to completed or failed.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'taskId' => $schema
                ->string()
                ->description('The UUID of the task (provided when the task was created for you).')
                ->required(),
            'status' => $schema
                ->string()
                ->description('Target status: completed or failed.')
                ->required(),
            'result' => $schema
                ->string()
                ->description('Result summary (when status is completed).'),
            'reason' => $schema
                ->string()
                ->description('Failure reason (when status is failed).'),
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

            $status = $request['status'] ?? '';

            return match ($status) {
                'completed' => $this->completeTask($task, $request['result'] ?? null),
                'failed' => $this->failTask($task, $request['reason'] ?? null),
                default => "Error: status must be 'completed' or 'failed'.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function completeTask(Task $task, mixed $result): string
    {
        $resultArray = is_array($result) ? $result : ($result ? ['summary' => $result] : null);
        $task->complete($resultArray);

        return 'Task marked as completed.';
    }

    private function failTask(Task $task, ?string $reason): string
    {
        $task->fail($reason);

        return 'Task marked as failed.' . ($reason ? " Reason: {$reason}" : '');
    }
}
