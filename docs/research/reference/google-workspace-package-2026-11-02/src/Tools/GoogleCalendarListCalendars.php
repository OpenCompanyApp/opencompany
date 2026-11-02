<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;

class GoogleCalendarListCalendars implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function name(): string
    {
        return 'google_calendar_list_calendars';
    }

    public function description(): string
    {
        return 'List all Google Calendars the user has access to.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Calendar integration is not configured.');
            }

            $params = [];
            if (isset($args['max_results'])) {
                $params['maxResults'] = (int) $args['max_results'];
            }
            if (isset($args['page_token'])) {
                $params['pageToken'] = $args['page_token'];
            }

            $result = $this->service->listCalendars($params);
            $items = $result['items'] ?? [];

            if (empty($items)) {
                return ToolResult::success('No calendars found.');
            }

            $calendars = array_map(fn (array $cal) => [
                'id' => $cal['id'] ?? '',
                'summary' => $cal['summary'] ?? '',
                'primary' => $cal['primary'] ?? false,
                'accessRole' => $cal['accessRole'] ?? '',
                'backgroundColor' => $cal['backgroundColor'] ?? '',
            ], $items);

            $output = ['count' => count($calendars), 'calendars' => $calendars];
            if (isset($result['nextPageToken'])) {
                $output['nextPageToken'] = $result['nextPageToken'];
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'max_results' => ['type' => 'integer', 'description' => 'Max results to return (default: 25, max: 250).'],
            'page_token' => ['type' => 'string', 'description' => 'Pagination token from previous response.'],
        ];
    }
}
