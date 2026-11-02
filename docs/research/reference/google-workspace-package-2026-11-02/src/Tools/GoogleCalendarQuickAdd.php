<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleCalendarService;

class GoogleCalendarQuickAdd implements Tool
{
    public function __construct(
        private GoogleCalendarService $service,
    ) {}

    public function name(): string
    {
        return 'google_calendar_quick_add';
    }

    public function description(): string
    {
        return 'Create a Google Calendar event from natural language text (e.g., "Lunch with Alice tomorrow at noon").';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Calendar integration is not configured.');
            }

            $calendarId = $args['calendar_id'] ?? 'primary';
            $text = $args['text'] ?? '';

            if (empty($text)) {
                return ToolResult::error('text is required (e.g., "Lunch with Alice tomorrow at noon").');
            }

            $event = $this->service->quickAddEvent($calendarId, $text);

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
            'text' => ['type' => 'string', 'required' => true, 'description' => 'Natural language event text (e.g., "Lunch with Alice tomorrow at noon").'],
        ];
    }
}
