<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use Illuminate\Http\Request;

class CalendarEventAttendeeController extends Controller
{
    public function store(Request $request, string $eventId): mixed
    {
        $event = CalendarEvent::findOrFail($eventId);

        $request->validate([
            'userId' => 'required|string|exists:users,id',
        ]);

        $attendee = $event->attendees()->create([
            'user_id' => $request->input('userId'),
            'status' => 'pending',
        ]);

        return $attendee->load('user');
    }

    public function update(Request $request, string $eventId, string $attendeeId): mixed
    {
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

    public function destroy(string $eventId, string $attendeeId): \Illuminate\Http\JsonResponse
    {
        CalendarEventAttendee::where('event_id', $eventId)
            ->findOrFail($attendeeId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
