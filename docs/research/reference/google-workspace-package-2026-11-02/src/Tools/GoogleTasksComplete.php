<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksComplete implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_complete';
    }

    public function description(): string
    {
        return 'Mark a Google Task as completed.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Tasks integration is not configured.');
            }

            $taskId = $args['task_id'] ?? '';
            if (empty($taskId)) {
                return ToolResult::error('taskId is required.');
            }

            $listId = $args['list_id'] ?? '@default';

            $task = $this->service->updateTask($listId, $taskId, [
                'status' => 'completed',
            ]);

            $title = $task['title'] ?? '';

            return ToolResult::success("Task completed: \"{$title}\"");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'task_id' => ['type' => 'string', 'required' => true, 'description' => 'Task ID to complete.'],
            'list_id' => ['type' => 'string', 'description' => 'Task list ID (default: "@default").'],
        ];
    }
}
