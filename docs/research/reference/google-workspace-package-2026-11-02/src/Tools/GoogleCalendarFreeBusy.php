<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;

class GoogleCalendarFreeBusy implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function name(): string
    {
        return 'google_calendar_freebusy';
    }

    public function description(): string
    {
        return <<<'MD'
        Check free/busy availability across one or more Google Calendars.
        Returns busy time slots within the specified time range.
        Useful for finding open slots for scheduling meetings.
        MD;
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Calendar integration is not configured.');
            }

            $timeMin = $args['time_min'] ?? '';
            $timeMax = $args['time_max'] ?? '';

            if (empty($timeMin) || empty($timeMax)) {
                return ToolResult::error('timeMin and timeMax are required (ISO 8601 format).');
            }

            $calendarIds = $args['calendar_ids'] ?? 'primary';
            $ids = array_map('trim', explode(',', $calendarIds));
            $items = array_map(fn (string $id) => ['id' => $id], $ids);

            $result = $this->service->queryFreeBusy([
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'items' => $items,
            ]);

            $calendars = $result['calendars'] ?? [];
            if (empty($calendars)) {
                return ToolResult::success('No free/busy data returned.');
            }

            $output = [];
            foreach ($calendars as $calId => $calData) {
                $busy = $calData['busy'] ?? [];
                $errors = $calData['errors'] ?? [];

                $output[$calId] = [
                    'busySlots' => count($busy),
                    'busy' => array_map(fn (array $slot) => [
                        'start' => $slot['start'] ?? '',
                        'end' => $slot['end'] ?? '',
                    ], $busy),
                ];

                if (! empty($errors)) {
                    $output[$calId]['errors'] = $errors;
                }
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'time_min' => ['type' => 'string', 'required' => true, 'description' => 'ISO 8601 start of query range (e.g., "2026-02-14T08:00:00Z").'],
            'time_max' => ['type' => 'string', 'required' => true, 'description' => 'ISO 8601 end of query range (e.g., "2026-02-14T18:00:00Z").'],
            'calendar_ids' => ['type' => 'string', 'description' => 'Comma-separated calendar IDs to check (default: "primary").'],
        ];
    }
}
