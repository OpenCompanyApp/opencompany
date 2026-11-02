<?php

namespace App\Agents\Tools\Tasks;

use App\Agents\Runtime\ActiveAgentRun;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Lets an agent finish or fail a task assigned to it.
 *
 * Invariant: an agent cannot terminate the task that its CURRENT run is
 * executing. That task is finished by the run itself after the final reply is
 * delivered; terminating it mid-run is indistinguishable from an external
 * cancellation at the next checkpoint and would drop the reply. Such calls
 * record the reported result on the task and tell the model to reply instead.
 *
 * Workspace boundary: tasks are resolved within the agent's workspace only.
 */
class SetTaskStatus implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Set the final status of a task assigned to you (completed or failed), such as a delegated or background task. '
            .'Do not use it for the chat request you are answering right now: that task closes automatically after your reply.';
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

            $task = Task::query()->where('workspace_id', $this->agent->workspace_id)->find($taskId);
            if (!$task) {
                return "Error: Task '{$taskId}' not found.";
            }

            if ($task->agent_id !== $this->agent->id) {
                return "Error: This task is not assigned to you.";
            }

            $status = $request['status'] ?? '';

            if (in_array($status, ['completed', 'failed'], true) && $this->isCurrentRunTask($task)) {
                $task->update(['context' => array_merge($task->context ?? [], [
                    'agent_reported_status' => $status,
                    'agent_reported_result' => $status === 'completed' ? ($request['result'] ?? null) : ($request['reason'] ?? null),
                ])]);

                return 'Recorded. This is the task your current run is executing; it is closed automatically after your final reply. '
                    .'Do not call set_task_status for it again — finish by replying to the user with the answer.';
            }

            return match ($status) {
                'completed' => $this->completeTask($task, $request['result'] ?? null),
                'failed' => $this->failTask($task, $request['reason'] ?? null),
                default => "Error: status must be 'completed' or 'failed'.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function isCurrentRunTask(Task $task): bool
    {
        if (! app()->bound(ActiveAgentRun::class)) {
            return false;
        }

        try {
            return app(ActiveAgentRun::class)->record()->task_id === $task->id;
        } catch (\Throwable) {
            return false;
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
