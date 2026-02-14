<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageCalendarEvent implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create, update, or delete calendar events. Use this to schedule meetings, deadlines, and other time-based events.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'create' => $this->create($request),
                'update' => $this->update($request),
                'delete' => $this->delete($request),
                default => "Unknown action: {$action}. Use 'create', 'update', or 'delete'.",
            };
        } catch (\Throwable $e) {
            return "Error managing calendar event: {$e->getMessage()}";
        }
    }

    private function create(Request $request): string
    {
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
    }

    private function update(Request $request): string
    {
        $event = CalendarEvent::findOrFail($request['eventId']);

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
    }

    private function delete(Request $request): string
    {
        $event = CalendarEvent::findOrFail($request['eventId']);
        $title = $event->title;
        $event->delete();

        return "Event '{$title}' deleted.";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action to perform: 'create', 'update', or 'delete'.")
                ->required(),
            'eventId' => $schema
                ->string()
                ->description('The UUID of the event. Required for update and delete.'),
            'title' => $schema
                ->string()
                ->description('The event title. Required for create.'),
            'description' => $schema
                ->string()
                ->description('A description of the event.'),
            'startAt' => $schema
                ->string()
                ->description('The event start time in ISO 8601 format. Required for create.'),
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
