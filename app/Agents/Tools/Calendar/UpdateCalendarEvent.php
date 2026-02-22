<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateCalendarEvent implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Update an existing calendar event. You can modify any combination of its properties including title, time, location, recurrence, and attendees.';
    }

    public function handle(Request $request): string
    {
        try {
            $event = CalendarEvent::forWorkspace()->findOrFail($request['eventId']);

            if (isset($request['title'])) {
                $event->title = $request['title'];
            }

            if (isset($request['description'])) {
                $event->description = $request['description'];
            }

            if (isset($request['startAt'])) {
                $event->start_at = $request['startAt'];
            }

            if (isset($request['endAt'])) {
                $event->end_at = $request['endAt'];
            }

            if (isset($request['allDay'])) {
                $event->all_day = $request['allDay'];
            }

            if (isset($request['location'])) {
                $event->location = $request['location'];
            }

            if (isset($request['color'])) {
                $event->color = $request['color'];
            }

            if (isset($request['recurrenceRule'])) {
                $event->recurrence_rule = $request['recurrenceRule'];
            }

            if (isset($request['recurrenceEnd'])) {
                $event->recurrence_end = $request['recurrenceEnd'];
            }

            $event->save();

            if (isset($request['attendeeIds'])) {
                $event->attendees()->delete();

                if ($request['attendeeIds'] !== '') {
                    $userIds = array_map('trim', explode(',', $request['attendeeIds']));

                    foreach ($userIds as $userId) {
                        CalendarEventAttendee::create([
                            'event_id' => $event->id,
                            'user_id' => $userId,
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            return 'Event updated.';
        } catch (\Throwable $e) {
            return "Error updating calendar event: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'eventId' => $schema
                ->string()
                ->description('The UUID of the event to update.')
                ->required(),
            'title' => $schema
                ->string()
                ->description('The new event title.'),
            'description' => $schema
                ->string()
                ->description('A new description for the event.'),
            'startAt' => $schema
                ->string()
                ->description('The new event start time in ISO 8601 format.'),
            'endAt' => $schema
                ->string()
                ->description('The new event end time in ISO 8601 format.'),
            'allDay' => $schema
                ->boolean()
                ->description('Whether the event spans the entire day.'),
            'location' => $schema
                ->string()
                ->description('The new event location.'),
            'color' => $schema
                ->string()
                ->description('Event color: blue, green, red, purple, yellow, orange, pink, indigo.'),
            'recurrenceRule' => $schema
                ->string()
                ->description('Cron expression for recurring events (e.g. "0 9 * * 1" for every Monday at 9am, "0 10 * * 1-5" for weekdays at 10am).'),
            'recurrenceEnd' => $schema
                ->string()
                ->description('ISO 8601 date when the recurrence stops (e.g. "2026-12-31").'),
            'attendeeIds' => $schema
                ->string()
                ->description('Comma-separated UUIDs of users to invite as attendees. Pass empty string to remove all attendees.'),
        ];
    }
}
