<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateCalendarEvent implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Create a new calendar event. Use this to schedule meetings, deadlines, and other time-based events.';
    }

    public function handle(Request $request): string
    {
        try {
            $event = CalendarEvent::create([
                'title' => $request['title'],
                'description' => $request['description'] ?? null,
                'start_at' => $request['startAt'],
                'end_at' => $request['endAt'] ?? null,
                'all_day' => $request['allDay'] ?? false,
                'location' => $request['location'] ?? null,
                'color' => $request['color'] ?? null,
                'recurrence_rule' => $request['recurrenceRule'] ?? null,
                'recurrence_end' => $request['recurrenceEnd'] ?? null,
                'created_by' => $this->agent->id,
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
            ]);

            if (isset($request['attendeeIds']) && $request['attendeeIds'] !== '') {
                $userIds = array_map('trim', explode(',', $request['attendeeIds']));

                foreach ($userIds as $userId) {
                    CalendarEventAttendee::create([
                        'event_id' => $event->id,
                        'user_id' => $userId,
                        'status' => 'pending',
                    ]);
                }
            }

            $recurrence = $event->recurrence_rule ? " (recurring: {$event->recurrence_rule})" : '';

            return "Event created: '{$event->title}' (ID: {$event->id}){$recurrence}";
        } catch (\Throwable $e) {
            return "Error creating calendar event: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema
                ->string()
                ->description('The event title.')
                ->required(),
            'startAt' => $schema
                ->string()
                ->description('The event start time in ISO 8601 format.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('A description of the event.'),
            'endAt' => $schema
                ->string()
                ->description('The event end time in ISO 8601 format.'),
            'allDay' => $schema
                ->boolean()
                ->description('Whether the event spans the entire day.'),
            'location' => $schema
                ->string()
                ->description('The event location.'),
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
                ->description('Comma-separated UUIDs of users to invite as attendees.'),
        ];
    }
}
