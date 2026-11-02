<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksUpdate implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_update';
    }

    public function description(): string
    {
        return 'Update task fields in Google Tasks. At least one field to update is required.';
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

            $data = [];

            if (isset($args['title'])) {
                $data['title'] = $args['title'];
            }

            if (isset($args['notes'])) {
                $data['notes'] = $args['notes'];
            }

            if (isset($args['due'])) {
                $due = $args['due'];
                $data['due'] = ! empty($due) ? $due . 'T00:00:00.000Z' : null;
            }

            if (isset($args['status'])) {
                $data['status'] = $args['status'];
            }

            if (empty($data)) {
                return ToolResult::error('at least one field to update is required (title, notes, due, status).');
            }

            $task = $this->service->updateTask($listId, $taskId, $data);
            $result = GoogleTasksService::formatTask($task);

            return ToolResult::success("Task updated: \"{$result['title']}\" — status: {$result['status']}" .
                (! empty($result['due']) ? ", due: {$result['due']}" : ''));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'task_id' => ['type' => 'string', 'required' => true, 'description' => 'Task ID to update.'],
            'list_id' => ['type' => 'string', 'description' => 'Task list ID (default: "@default").'],
            'title' => ['type' => 'string', 'description' => 'New task title.'],
            'notes' => ['type' => 'string', 'description' => 'Task notes/description (max 8192 chars).'],
            'due' => ['type' => 'string', 'description' => 'Due date in YYYY-MM-DD format. Set empty string to clear.'],
            'status' => ['type' => 'string', 'description' => 'Task status: "needsAction" (open) or "completed".'],
        ];
    }
}
