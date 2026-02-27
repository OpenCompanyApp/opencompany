<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateCalendarAttendee implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Update an attendee\'s RSVP status on a calendar event (e.g. accepted, declined, tentative).';
    }

    public function handle(Request $request): string
    {
        try {
            $event = CalendarEvent::forWorkspace()->findOrFail($request['eventId']);

            $attendee = CalendarEventAttendee::where('event_id', $event->id)
                ->findOrFail($request['attendeeId']);

            $attendee->update([
                'status' => $request['status'],
            ]);

            $attendee->load('user:id,name');

            return json_encode([
                'id' => $attendee->id,
                'userId' => $attendee->user_id,
                'name' => $attendee->user?->name ?? 'Unknown',
                'status' => $attendee->status,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error updating attendee: {$e->getMessage()}";
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
                ->description('The UUID of the attendee record to update.')
                ->required(),
            'status' => $schema
                ->string()
                ->description('The new RSVP status: pending, accepted, declined, or tentative.')
                ->required(),
        ];
    }
}
