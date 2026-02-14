<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryCalendar implements Tool
{

    public function description(): string
    {
        return 'Query calendar events. List events within a date range or get details for a specific event.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'list_events' => $this->listEvents($request),
                'get_event' => $this->getEvent($request),
                default => "Error: Unknown action '{$action}'. Use 'list_events' or 'get_event'.",
            };
        } catch (\Throwable $e) {
            return "Error querying calendar: {$e->getMessage()}";
        }
    }

    private function listEvents(Request $request): string
    {
        $startDate = $request['startDate'] ?? null;
        $endDate = $request['endDate'] ?? null;
        $userId = $request['userId'] ?? null;

        $query = CalendarEvent::with(['creator', 'attendees.user']);

        if ($startDate) {
            $query->where('start_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_at', '<=', $endDate);
        }

        if ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('attendees', function ($aq) use ($userId) {
                        $aq->where('user_id', $userId);
                    });
            });
        }

        $events = $query->orderBy('start_at', 'asc')->get();

        if ($events->isEmpty()) {
            return "No calendar events found matching the criteria.";
        }

        $lines = ["Calendar events ({$events->count()}):"];
        foreach ($events as $event) {
            $date = $event->all_day
                ? $event->start_at->format('Y-m-d') . ' (all day)'
                : $event->start_at->format('Y-m-d H:i') . ' - ' . ($event->end_at ? $event->end_at->format('H:i') : 'TBD');
            $location = $event->location ? " | Location: {$event->location}" : '';
            $recurrence = $event->recurrence_rule ? " | Recurring: {$event->recurrence_rule}" : '';
            $attendeeCount = $event->attendees->count();
            $lines[] = "- {$event->title} | {$date}{$location}{$recurrence} | {$attendeeCount} attendee(s)";
            $lines[] = "  ID: {$event->id}";
        }

        return implode("\n", $lines);
    }

    private function getEvent(Request $request): string
    {
        $eventId = $request['eventId'] ?? null;
        if (!$eventId) {
            return "Error: 'eventId' is required for the 'get_event' action.";
        }

        $event = CalendarEvent::with(['creator', 'attendees.user'])->find($eventId);
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
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The query action: 'list_events' or 'get_event'.")
                ->required(),
            'eventId' => $schema
                ->string()
                ->description('The UUID of the event. Required for get_event.'),
            'startDate' => $schema
                ->string()
                ->description('Filter events starting from this date (ISO 8601 format). Used with list_events.'),
            'endDate' => $schema
                ->string()
                ->description('Filter events up to this date (ISO 8601 format). Used with list_events.'),
            'userId' => $schema
                ->string()
                ->description('Filter events by user (as creator or attendee). Used with list_events.'),
        ];
    }
}
