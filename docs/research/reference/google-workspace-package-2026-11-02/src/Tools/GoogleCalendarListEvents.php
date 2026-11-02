<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;

class GoogleCalendarListEvents implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function name(): string
    {
        return 'google_calendar_list_events';
    }

    public function description(): string
    {
        return 'List or search events in a Google Calendar. Supports date range filtering and text search.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Calendar integration is not configured.');
            }

            $calendarId = $args['calendar_id'] ?? 'primary';

            $params = [
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
            ];

            if (isset($args['time_min'])) {
                $params['timeMin'] = $args['time_min'];
            }
            if (isset($args['time_max'])) {
                $params['timeMax'] = $args['time_max'];
            }
            if (isset($args['query'])) {
                $params['q'] = $args['query'];
            }
            if (isset($args['max_results'])) {
                $params['maxResults'] = (string) (int) $args['max_results'];
            }
            if (isset($args['page_token'])) {
                $params['pageToken'] = $args['page_token'];
            }

            $result = $this->service->listEvents($calendarId, $params);
            $items = $result['items'] ?? [];

            if (empty($items)) {
                return ToolResult::success('No events found.');
            }

            $events = array_map(fn (array $event) => [
                'id' => $event['id'] ?? '',
                'summary' => $event['summary'] ?? '(No title)',
                'start' => $event['start']['dateTime'] ?? $event['start']['date'] ?? '',
                'end' => $event['end']['dateTime'] ?? $event['end']['date'] ?? '',
                'location' => $event['location'] ?? null,
                'status' => $event['status'] ?? '',
                'htmlLink' => $event['htmlLink'] ?? '',
            ], $items);

            // Remove null values
            $events = array_map(fn (array $e) => array_filter($e, fn ($v) => $v !== null), $events);

            $output = ['count' => count($events), 'events' => $events];
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
            'calendar_id' => ['type' => 'string', 'description' => 'Calendar ID (default: "primary").'],
            'time_min' => ['type' => 'string', 'description' => 'ISO 8601 start filter (e.g., "2026-02-14T00:00:00Z").'],
            'time_max' => ['type' => 'string', 'description' => 'ISO 8601 end filter (e.g., "2026-02-21T23:59:59Z").'],
            'query' => ['type' => 'string', 'description' => 'Free text search within events.'],
            'max_results' => ['type' => 'integer', 'description' => 'Max results to return (default: 25, max: 250).'],
            'page_token' => ['type' => 'string', 'description' => 'Pagination token from previous response.'],
        ];
    }
}
