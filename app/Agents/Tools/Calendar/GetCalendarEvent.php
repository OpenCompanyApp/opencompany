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

            $creator = ($event->creator ? $event->creator->name : 'Unknown');
            $date = $event->all_day
                ? $event->start_at->format('Y-m-d') . ' (all day)'
                : $event->start_at->format('Y-m-d H:i') . ' - ' . ($event->end_at ? $event->end_at->format('Y-m-d H:i') : 'TBD');

            $lines = [
                "Event: {$event->title}",
                "Date: {$date}",
                "Location: " . ($event->location ?? 'None'),
                "Description: " . ($event->description ?? 'None'),
                "Created by: {$creator}",
                "Recurrence: " . ($event->recurrence_rule ?? 'None'),
                "Recurrence End: " . ($event->recurrence_end ? $event->recurrence_end->format('Y-m-d') : 'None'),
            ];

            if ($event->attendees->isNotEmpty()) {
                $lines[] = "Attendees ({$event->attendees->count()}):";
                foreach ($event->attendees as $attendee) {
                    $name = ($attendee->user ? $attendee->user->name : 'Unknown');
                    $status = $attendee->status ?? 'pending';
                    $lines[] = "- {$name} ({$status})";
                }
            } else {
                $lines[] = "Attendees: None";
            }

            return implode("\n", $lines);
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