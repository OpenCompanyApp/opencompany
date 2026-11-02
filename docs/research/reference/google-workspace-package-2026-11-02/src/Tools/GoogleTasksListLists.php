<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleTasksService;

class GoogleTasksListLists implements Tool
{
    public function __construct(
        private GoogleTasksService $service,
    ) {}

    public function name(): string
    {
        return 'google_tasks_list_lists';
    }

    public function description(): string
    {
        return 'List all Google Task lists. Returns IDs and titles. Start here to discover available lists.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Tasks integration is not configured.');
            }

            $maxResults = isset($args['max_results']) ? (int) $args['max_results'] : null;
            $pageToken = $args['page_token'] ?? null;

            $result = $this->service->listTaskLists($maxResults, $pageToken);
            $items = $result['items'] ?? [];

            if (empty($items)) {
                return ToolResult::success('No task lists found.');
            }

            $lists = [];
            foreach ($items as $list) {
                $lists[] = [
                    'id' => $list['id'] ?? '',
                    'title' => $list['title'] ?? '',
                    'updated' => isset($list['updated']) ? substr((string) $list['updated'], 0, 10) : null,
                ];
            }

            $output = ['count' => count($lists), 'lists' => $lists];

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
            'max_results' => ['type' => 'integer', 'description' => 'Maximum number of results (default: 100, max: 100).'],
            'page_token' => ['type' => 'string', 'description' => 'Page token for pagination (from previous response).'],
        ];
    }
}
