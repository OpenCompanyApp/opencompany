<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;

class GoogleCalendarCreateEvent implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function name(): string
    {
        return 'google_calendar_create_event';
    }

    public function description(): string
    {
        return 'Create a Google Calendar event. Use startDateTime/endDateTime for timed events, or startDate/endDate for all-day events.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Calendar integration is not configured.');
            }

            $calendarId = $args['calendar_id'] ?? 'primary';
            $summary = $args['summary'] ?? '';

            if (empty($summary)) {
                return ToolResult::error('summary is required.');
            }

            $data = ['summary' => $summary];

            if (isset($args['description'])) {
                $data['description'] = $args['description'];
            }
            if (isset($args['location'])) {
                $data['location'] = $args['location'];
            }

            // Timed events
            if (isset($args['start_date_time'])) {
                $data['start'] = ['dateTime' => $args['start_date_time']];
                if (isset($args['time_zone'])) {
                    $data['start']['timeZone'] = $args['time_zone'];
                }
            } elseif (isset($args['start_date'])) {
                $data['start'] = ['date' => $args['start_date']];
            } else {
                return ToolResult::error('startDateTime or startDate is required.');
            }

            if (isset($args['end_date_time'])) {
                $data['end'] = ['dateTime' => $args['end_date_time']];
                if (isset($args['time_zone'])) {
                    $data['end']['timeZone'] = $args['time_zone'];
                }
            } elseif (isset($args['end_date'])) {
                $data['end'] = ['date' => $args['end_date']];
            } else {
                return ToolResult::error('endDateTime or endDate is required.');
            }

            // Attendees
            if (isset($args['attendees'])) {
                $emails = array_map('trim', explode(',', $args['attendees']));
                $data['attendees'] = array_map(fn (string $email) => ['email' => $email], $emails);
            }

            // Recurrence
            if (isset($args['recurrence'])) {
                $data['recurrence'] = [$args['recurrence']];
            }

            $event = $this->service->createEvent($calendarId, $data);

            return ToolResult::success($this->formatEvent($event));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function formatEvent(array $event): array
    {
        $formatted = [
            'id' => $event['id'] ?? '',
            'summary' => $event['summary'] ?? '(No title)',
            'start' => $event['start']['dateTime'] ?? $event['start']['date'] ?? '',
            'end' => $event['end']['dateTime'] ?? $event['end']['date'] ?? '',
            'status' => $event['status'] ?? '',
            'htmlLink' => $event['htmlLink'] ?? '',
        ];

        if (! empty($event['description'])) {
            $formatted['description'] = $event['description'];
        }
        if (! empty($event['location'])) {
            $formatted['location'] = $event['location'];
        }
        if (! empty($event['attendees'])) {
            $formatted['attendees'] = array_map(fn (array $a) => [
                'email' => $a['email'] ?? '',
                'responseStatus' => $a['responseStatus'] ?? '',
            ], $event['attendees']);
        }
        if (! empty($event['recurrence'])) {
            $formatted['recurrence'] = $event['recurrence'];
        }

        return $formatted;
    }

    public function parameters(): array
    {
        return [
            'calendar_id' => ['type' => 'string', 'description' => 'Calendar ID (default: "primary").'],
            'summary' => ['type' => 'string', 'required' => true, 'description' => 'Event title.'],
            'description' => ['type' => 'string', 'description' => 'Event description.'],
            'location' => ['type' => 'string', 'description' => 'Event location.'],
            'start_date_time' => ['type' => 'string', 'description' => 'ISO 8601 start time for timed events (e.g., "2026-02-15T10:00:00-05:00").'],
            'end_date_time' => ['type' => 'string', 'description' => 'ISO 8601 end time for timed events.'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD start date for all-day events.'],
            'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD end date for all-day events.'],
            'time_zone' => ['type' => 'string', 'description' => 'IANA timezone (e.g., "America/New_York"). Applied to start/end times.'],
            'attendees' => ['type' => 'string', 'description' => 'Comma-separated attendee email addresses.'],
            'recurrence' => ['type' => 'string', 'description' => 'RRULE recurrence string (e.g., "RRULE:FREQ=WEEKLY;COUNT=10").'],
        ];
    }
}
