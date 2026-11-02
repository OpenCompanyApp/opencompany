<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksClearCompleted implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_clear_completed';
    }

    public function description(): string
    {
        return 'Remove all completed tasks from a Google Tasks list. Warning: permanently deletes completed tasks.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Tasks integration is not configured.');
            }

            $listId = $args['list_id'] ?? '@default';

            $this->service->clearCompleted($listId);

            return ToolResult::success('All completed tasks cleared from list.');
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'list_id' => ['type' => 'string', 'description' => 'Task list ID (default: "@default").'],
        ];
    }
}
