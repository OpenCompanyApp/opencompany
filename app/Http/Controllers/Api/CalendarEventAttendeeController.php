<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API for mutating attendees on workspace calendar events.
 *
 * Every action first proves the parent event belongs to the current workspace;
 * attendee rows inherit tenant scope through that event.
 */
class CalendarEventAttendeeController extends Controller
{
    public function store(Request $request, string $eventId): mixed
    {
        $event = CalendarEvent::forWorkspace()->findOrFail($eventId);

        $request->validate([
            'userId' => 'required|string|exists:users,id',
        ]);

        // New attendees start pending until the human or agent updates RSVP
        // state through this controller or the matching agent tool.
        $attendee = $event->attendees()->create([
            'user_id' => $request->input('userId'),
            'status' => 'pending',
        ]);

        return $attendee->load('user');
    }

    public function update(Request $request, string $eventId, string $attendeeId): mixed
    {
        // Keep the workspace check on the event before looking up the attendee,
        // because attendees do not carry workspace_id directly.
        CalendarEvent::forWorkspace()->findOrFail($eventId);

        $attendee = CalendarEventAttendee::where('event_id', $eventId)
            ->findOrFail($attendeeId);

        $request->validate([
            'status' => 'required|in:pending,accepted,declined,tentative',
        ]);

        $attendee->update([
            'status' => $request->input('status'),
        ]);

        return $attendee->load('user');
    }

    public function destroy(string $eventId, string $attendeeId): JsonResponse
    {
        // Parent-event lookup is the workspace boundary for attendee deletion.
        CalendarEvent::forWorkspace()->findOrFail($eventId);

        CalendarEventAttendee::where('event_id', $eventId)
            ->findOrFail($attendeeId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
