<?php

namespace App\Agents\Tools;

use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateCurrentTask implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update your currently running task — set its title and description, add steps, update steps, or set its final status. Set description before completing to summarize what was done.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];
            $taskId = $request['taskId'];

            $task = Task::find($taskId);
            if (!$task) {
                return "Error: Task '{$taskId}' not found.";
            }

            if ($task->agent_id !== $this->agent->id) {
                return "Error: This task is not assigned to you.";
            }

            return match ($action) {
                'update_task' => $this->updateTask($task, $request),
                'add_step' => $this->addStep($task, $request),
                'update_step' => $this->updateStep($task, $request),
                'set_status' => $this->setStatus($task, $request),
                default => "Error: Unknown action '{$action}'. Use: update_task, add_step, update_step, set_status.",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function updateTask(Task $task, Request $request): string
    {
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
    }

    private function addStep(Task $task, Request $request): string
    {
        $name = trim($request['name'] ?? '');
        if ($name === '') {
            return 'Error: name must not be empty.';
        }

        $stepType = $request['stepType'] ?? 'action';

        $step = $task->addStep($name, $stepType);
        $step->start();

        return "Step added: '{$name}' (id: {$step->id})";
    }

    private function updateStep(Task $task, Request $request): string
    {
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
    }

    private function setStatus(Task $task, Request $request): string
    {
        $status = $request['status'] ?? '';
        $result = $request['result'] ?? null;

        return match ($status) {
            'completed' => $this->completeTask($task, $result),
            'failed' => $this->failTask($task, $request['reason'] ?? null),
            default => "Error: status must be 'completed' or 'failed'.",
        };
    }

    private function completeTask(Task $task, mixed $result): string
    {
        if (empty($task->description)) {
            return 'Error: set a description (via update_task) before completing. Summarize what was done.';
        }

        $resultArray = is_array($result) ? $result : ($result ? ['summary' => $result] : null);
        $task->complete($resultArray);

        return 'Task marked as completed.';
    }

    private function failTask(Task $task, ?string $reason): string
    {
        $task->fail($reason);

        return 'Task marked as failed.' . ($reason ? " Reason: {$reason}" : '');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description('Action to perform: update_task, add_step, update_step, or set_status.')
                ->required(),
            'taskId' => $schema
                ->string()
                ->description('The UUID of the task (provided when the task was created for you).')
                ->required(),
            'title' => $schema
                ->string()
                ->description('New task title (for update_task). Short, descriptive summary of the work.'),
            'description' => $schema
                ->string()
                ->description('Task description (for update_task) or new step description (for update_step).'),
            'name' => $schema
                ->string()
                ->description('Step name/description (for add_step action). Must not be empty.'),
            'stepId' => $schema
                ->string()
                ->description('Step UUID to update (for update_step action).'),
            'stepType' => $schema
                ->string()
                ->description('Step type for add_step: action, decision, approval, sub_task, or message. Default: action.'),
            'status' => $schema
                ->string()
                ->description('Target status for set_status action: completed or failed.'),
            'result' => $schema
                ->string()
                ->description('Result summary (for set_status with completed).'),
            'reason' => $schema
                ->string()
                ->description('Failure reason (for set_status with failed).'),
        ];
    }
}
