<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksListTasks implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_list_tasks';
    }

    public function description(): string
    {
        return 'List tasks in a Google Task list. Use "@default" as listId for the primary "My Tasks" list. Supports filtering by completion status and due date range.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Tasks integration is not configured.');
            }

            $listId = $args['list_id'] ?? '@default';

            $params = [];

            if (isset($args['show_completed'])) {
                $params['showCompleted'] = (bool) $args['show_completed'];
            }

            if (isset($args['show_hidden'])) {
                $params['showHidden'] = (bool) $args['show_hidden'];
            }

            if (isset($args['due_min'])) {
                $params['dueMin'] = $args['due_min'];
            }

            if (isset($args['due_max'])) {
                $params['dueMax'] = $args['due_max'];
            }

            if (isset($args['max_results'])) {
                $params['maxResults'] = (int) $args['max_results'];
            }

            if (isset($args['page_token'])) {
                $params['pageToken'] = $args['page_token'];
            }

            $result = $this->service->listTasks($listId, $params);
            $items = $result['items'] ?? [];

            if (empty($items)) {
                return ToolResult::success('No tasks found in this list.');
            }

            $tasks = array_map(
                fn (array $task) => GoogleTasksService::formatTask($task),
                $items
            );

            $output = ['count' => count($tasks), 'tasks' => $tasks];

            $nextPageToken = $result['nextPageToken'] ?? null;
            if ($nextPageToken) {
                $output['nextPageToken'] = $nextPageToken;
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'list_id' => ['type' => 'string', 'description' => 'Task list ID. Use "@default" for the primary "My Tasks" list.'],
            'show_completed' => ['type' => 'boolean', 'description' => 'Include completed tasks (default: false).'],
            'show_hidden' => ['type' => 'boolean', 'description' => 'Include hidden tasks (default: false).'],
            'due_min' => ['type' => 'string', 'description' => 'Filter tasks with due date on or after this date (YYYY-MM-DD).'],
            'due_max' => ['type' => 'string', 'description' => 'Filter tasks with due date on or before this date (YYYY-MM-DD).'],
            'max_results' => ['type' => 'integer', 'description' => 'Maximum number of results (default: 100, max: 100).'],
            'page_token' => ['type' => 'string', 'description' => 'Page token for pagination (from previous response).'],
        ];
    }
}
