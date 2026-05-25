<?php

namespace App\Http\Controllers\Api;

use App\Domain\Calendar\Application\ManageCalendarEvents;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP adapter for workspace calendar events.
 *
 * Request validation, upload/download response shaping, and remote ICS fetches
 * stay here. Calendar persistence, recurrence expansion, and ICS parsing live
 * in the domain application service so this controller remains transport-only.
 */
class CalendarEventController extends Controller
{
    public function index(Request $request, ManageCalendarEvents $calendar)
    {
        return $calendar->list(
            workspace(),
            $request->has('start') ? Carbon::parse($request->input('start')) : null,
            $request->has('end') ? Carbon::parse($request->input('end')) : null,
            $request->input('userId'),
        );
    }

    public function show(string $id, ManageCalendarEvents $calendar): CalendarEvent
    {
        return $calendar->show(workspace(), $id);
    }

    public function store(Request $request, ManageCalendarEvents $calendar): CalendarEvent
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'startAt' => 'required|date',
            'endAt' => 'nullable|date|after_or_equal:startAt',
            'color' => 'nullable|string|in:blue,green,red,purple,yellow,orange,pink,indigo',
            'recurrenceRule' => 'nullable|string|max:100',
            'recurrenceEnd' => 'nullable|date',
        ]);

        return $calendar->create(workspace(), $request->user(), array_merge($request->all(), $validated));
    }

    public function update(Request $request, string $id, ManageCalendarEvents $calendar): CalendarEvent|JsonResponse
    {
        $validated = $request->validate([
            'endAt' => 'nullable|date',
            'color' => 'nullable|string|in:blue,green,red,purple,yellow,orange,pink,indigo',
            'recurrenceRule' => 'nullable|string|max:100',
            'recurrenceEnd' => 'nullable|date',
        ]);

        $startAt = $request->input('startAt');
        $endAt = $request->input('endAt');
        if ($startAt && $endAt && strtotime($endAt) < strtotime($startAt)) {
            return response()->json(['message' => 'End date must be after or equal to start date.'], 422);
        }

        return $calendar->update(workspace(), $id, array_merge($request->all(), $validated));
    }

    public function destroy(string $id, ManageCalendarEvents $calendar): JsonResponse
    {
        $calendar->delete(workspace(), $id);

        return response()->json(['success' => true]);
    }

    public function export(Request $request, ManageCalendarEvents $calendar): Response
    {
        $content = $calendar->exportIcs(
            workspace(),
            $request->has('start') ? Carbon::parse($request->input('start')) : null,
            $request->has('end') ? Carbon::parse($request->input('end')) : null,
        );

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }

    public function import(Request $request, ManageCalendarEvents $calendar): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:ics,txt|max:2048',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        return $this->importIcsContent($calendar, $request->user(), $content ?: '');
    }

    public function importFromUrl(Request $request, ManageCalendarEvents $calendar): JsonResponse
    {
        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $response = Http::timeout(15)->get($request->input('url'));

        if (! $response->successful()) {
            return response()->json(['message' => 'Failed to fetch ICS from the provided URL.'], 422);
        }

        return $this->importIcsContent($calendar, $request->user(), $response->body());
    }

    private function importIcsContent(ManageCalendarEvents $calendar, User $user, string $content): JsonResponse
    {
        $events = $calendar->importIcsContent(workspace(), $user, $content);

        return response()->json([
            'imported' => $events->count(),
            'events' => $events,
        ]);
    }
}
