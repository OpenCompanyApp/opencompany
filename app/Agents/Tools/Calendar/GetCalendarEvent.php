<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCalendarEvent implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Get detailed information about a specific calendar event by its ID.';
    }

    public function handle(Request $request): string
    {
        try {
            $eventId = $request['eventId'] ?? null;
            if (!$eventId) {
                return "Error: 'eventId' is required for the 'get_event' action.";
            }

            $event = CalendarEvent::forWorkspace()->with(['creator', 'attendees.user'])->find($eventId);
            if (!$event) {
                return "Error: Event '{$eventId}' not found.";
            }

            $result = [
                'id' => $event->id,
                'title' => $event->title,
                'startAt' => $event->start_at->toIso8601String(),
                'endAt' => $event->end_at?->toIso8601String(),
                'allDay' => $event->all_day,
                'location' => $event->location,
                'description' => $event->description,
                'createdBy' => $event->creator?->name ?? 'Unknown',
                'recurrenceRule' => $event->recurrence_rule,
                'recurrenceEnd' => $event->recurrence_end?->toIso8601String(),
                'attendees' => $event->attendees->map(fn ($a) => [
                    'userId' => $a->user_id,
                    'name' => $a->user?->name ?? 'Unknown',
                    'status' => $a->status ?? 'pending',
                ])->values()->toArray(),
            ];

            return json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error getting calendar event: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'eventId' => $schema
                ->string()
                ->description('The UUID of the event to retrieve.')
                ->required(),
        ];
    }
}