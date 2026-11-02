<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;

class GoogleCalendarDeleteEvent implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function name(): string
    {
        return 'google_calendar_delete_event';
    }

    public function description(): string
    {
        return 'Delete a Google Calendar event by its ID.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Calendar integration is not configured.');
            }

            $calendarId = $args['calendar_id'] ?? 'primary';
            $eventId = $args['event_id'] ?? '';

            if (empty($eventId)) {
                return ToolResult::error('eventId is required.');
            }

            $this->service->deleteEvent($calendarId, $eventId);

            return ToolResult::success("Event '{$eventId}' deleted successfully.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'calendar_id' => ['type' => 'string', 'description' => 'Calendar ID (default: "primary").'],
            'event_id' => ['type' => 'string', 'required' => true, 'description' => 'Event ID to delete.'],
        ];
    }
}
