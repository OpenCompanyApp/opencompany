<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksMove implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_move';
    }

    public function description(): string
    {
        return 'Reorder or reparent a Google Task. Use parent to set a new parent (empty string moves to top level), and previous to position after a sibling.';
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

            $parent = isset($args['parent']) ? (string) $args['parent'] : null;
            $previous = isset($args['previous']) ? (string) $args['previous'] : null;

            if ($parent === null && $previous === null) {
                return ToolResult::error('at least one of parent or previous is required.');
            }

            // Empty string for parent means "move to top level" -- pass it to the API
            $task = $this->service->moveTask($listId, $taskId, $parent, $previous);
            $result = GoogleTasksService::formatTask($task);

            $description = "Task moved: \"{$result['title']}\"";
            if ($parent !== null && $parent !== '') {
                $description .= " — now subtask of {$parent}";
            } elseif ($parent === '') {
                $description .= ' — moved to top level';
            }

            return ToolResult::success($description);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'task_id' => ['type' => 'string', 'required' => true, 'description' => 'Task ID to move.'],
            'list_id' => ['type' => 'string', 'description' => 'Task list ID (default: "@default").'],
            'parent' => ['type' => 'string', 'description' => 'New parent task ID. Empty string moves to top level.'],
            'previous' => ['type' => 'string', 'description' => 'Sibling task ID to insert after.'],
        ];
    }
}
