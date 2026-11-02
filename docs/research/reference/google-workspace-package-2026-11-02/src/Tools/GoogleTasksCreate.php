<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksCreate implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_create';
    }

    public function description(): string
    {
        return 'Create a task in Google Tasks. Use "@default" as listId for the primary "My Tasks" list.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Tasks integration is not configured.');
            }

            $title = $args['title'] ?? '';
            if (empty($title)) {
                return ToolResult::error('title is required.');
            }

            $listId = $args['list_id'] ?? '@default';

            $data = ['title' => $title];

            if (! empty($args['notes'])) {
                $data['notes'] = $args['notes'];
            }

            if (! empty($args['due'])) {
                $data['due'] = $args['due'] . 'T00:00:00.000Z';
            }

            $parent = ! empty($args['parent']) ? $args['parent'] : null;

            $task = $this->service->createTask($listId, $data, $parent);

            $result = GoogleTasksService::formatTask($task);

            return ToolResult::success("Task created: \"{$result['title']}\" (ID: {$result['id']})" .
                (! empty($result['due']) ? " — due {$result['due']}" : '') .
                ($parent ? ' — subtask' : ''));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Task title.'],
            'list_id' => ['type' => 'string', 'description' => 'Task list ID (default: "@default" for primary "My Tasks" list).'],
            'notes' => ['type' => 'string', 'description' => 'Task notes/description (max 8192 chars).'],
            'due' => ['type' => 'string', 'description' => 'Due date in YYYY-MM-DD format.'],
            'parent' => ['type' => 'string', 'description' => 'Parent task ID to create as subtask.'],
        ];
    }
}
