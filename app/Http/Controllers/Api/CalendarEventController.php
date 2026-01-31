<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function index(Request $request)
    {
        $query = CalendarEvent::with(['creator', 'attendees.user']);

        // Filter by date range if provided
        if ($request->has('start')) {
            $query->where('start_at', '>=', $request->input('start'));
        }
        if ($request->has('end')) {
            $query->where('start_at', '<=', $request->input('end'));
        }

        // Filter by user if provided
        if ($request->has('userId')) {
            $userId = $request->input('userId');
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('attendees', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
            });
        }

        return $query->orderBy('start_at')->get();
    }

    public function show(string $id)
    {
        return CalendarEvent::with(['creator', 'attendees.user'])
            ->findOrFail($id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'startAt' => 'required|date',
        ]);

        $event = CalendarEvent::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'start_at' => $request->input('startAt'),
            'end_at' => $request->input('endAt'),
            'all_day' => $request->input('allDay', false),
            'location' => $request->input('location'),
            'color' => $request->input('color'),
            'recurrence_rule' => $request->input('recurrenceRule'),
            'created_by' => $request->input('createdBy', auth()->id()),
        ]);

        // Add attendees
        if ($request->has('attendeeIds')) {
            foreach ($request->input('attendeeIds') as $userId) {
                $event->attendees()->create([
                    'user_id' => $userId,
                    'status' => 'pending',
                ]);
            }
        }

        return $event->load(['creator', 'attendees.user']);
    }

    public function update(Request $request, string $id)
    {
        $event = CalendarEvent::findOrFail($id);

        $data = [];

        if ($request->has('title')) {
            $data['title'] = $request->input('title');
        }
        if ($request->has('description')) {
            $data['description'] = $request->input('description');
        }
        if ($request->has('startAt')) {
            $data['start_at'] = $request->input('startAt');
        }
        if ($request->has('endAt')) {
            $data['end_at'] = $request->input('endAt');
        }
        if ($request->has('allDay')) {
            $data['all_day'] = $request->input('allDay');
        }
        if ($request->has('location')) {
            $data['location'] = $request->input('location');
        }
        if ($request->has('color')) {
            $data['color'] = $request->input('color');
        }
        if ($request->has('recurrenceRule')) {
            $data['recurrence_rule'] = $request->input('recurrenceRule');
        }

        $event->update($data);

        // Update attendees if provided
        if ($request->has('attendeeIds')) {
            $event->attendees()->delete();
            foreach ($request->input('attendeeIds') as $userId) {
                $event->attendees()->create([
                    'user_id' => $userId,
                    'status' => 'pending',
                ]);
            }
        }

        return $event->load(['creator', 'attendees.user']);
    }

    public function destroy(string $id)
    {
        CalendarEvent::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
