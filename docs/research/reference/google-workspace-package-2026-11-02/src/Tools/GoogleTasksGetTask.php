<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksGetTask implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_get_task';
    }

    public function description(): string
    {
        return 'Get full details of a single Google Task by its ID.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Tasks integration is not configured.');
            }

            $listId = $args['list_id'] ?? '@default';
            $taskId = $args['task_id'] ?? '';

            if (empty($taskId)) {
                return ToolResult::error('taskId is required.');
            }

            $task = $this->service->getTask($listId, $taskId);

            return ToolResult::success(GoogleTasksService::formatTask($task));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'list_id' => ['type' => 'string', 'description' => 'Task list ID. Use "@default" for the primary "My Tasks" list.'],
            'task_id' => ['type' => 'string', 'required' => true, 'description' => 'Task ID to retrieve.'],
        ];
    }
}
