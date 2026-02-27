<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RemoveCalendarAttendee implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Remove an attendee from a calendar event.';
    }

    public function handle(Request $request): string
    {
        try {
            $event = CalendarEvent::forWorkspace()->findOrFail($request['eventId']);

            CalendarEventAttendee::where('event_id', $event->id)
                ->findOrFail($request['attendeeId'])
                ->delete();

            return json_encode(['success' => true], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error removing attendee: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'eventId' => $schema
                ->string()
                ->description('The UUID of the calendar event.')
                ->required(),
            'attendeeId' => $schema
                ->string()
                ->description('The UUID of the attendee record to remove.')
                ->required(),
        ];
    }
}
