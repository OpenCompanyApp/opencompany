<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CalendarEventController extends Controller
{
    public function index(Request $request)
    {
        $rangeStart = $request->has('start') ? Carbon::parse($request->input('start')) : null;
        $rangeEnd = $request->has('end') ? Carbon::parse($request->input('end')) : null;

        // Non-recurring events
        $query = CalendarEvent::with(['creator', 'attendees.user'])
            ->whereNull('recurrence_rule');

        if ($rangeStart) {
            $query->where('start_at', '>=', $rangeStart);
        }
        if ($rangeEnd) {
            $query->where('start_at', '<=', $rangeEnd);
        }

        if ($request->has('userId')) {
            $userId = $request->input('userId');
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('attendees', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
            });
        }

        $events = $query->orderBy('start_at')->get();

        // Recurring events — expand within the requested range
        if ($rangeStart && $rangeEnd) {
            $recurringQuery = CalendarEvent::with(['creator', 'attendees.user'])
                ->whereNotNull('recurrence_rule')
                ->where('start_at', '<=', $rangeEnd)
                ->where(function ($q) use ($rangeStart) {
                    $q->whereNull('recurrence_end')
                        ->orWhere('recurrence_end', '>=', $rangeStart);
                });

            if ($request->has('userId')) {
                $userId = $request->input('userId');
                $recurringQuery->where(function ($q) use ($userId) {
                    $q->where('created_by', $userId)
                        ->orWhereHas('attendees', function ($q) use ($userId) {
                            $q->where('user_id', $userId);
                        });
                });
            }

            $recurringEvents = $recurringQuery->get();
            $expanded = $this->expandRecurringEvents($recurringEvents, $rangeStart, $rangeEnd);
            $events = $events->concat($expanded)->sortBy('start_at')->values();
        }

        return $events;
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
            'endAt' => 'nullable|date|after_or_equal:startAt',
            'color' => 'nullable|string|in:blue,green,red,purple,yellow,orange,pink,indigo',
            'recurrenceRule' => 'nullable|string|max:100',
            'recurrenceEnd' => 'nullable|date',
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
            'recurrence_end' => $request->input('recurrenceEnd'),
            'created_by' => auth()->id(),
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
        $request->validate([
            'endAt' => 'nullable|date',
            'color' => 'nullable|string|in:blue,green,red,purple,yellow,orange,pink,indigo',
            'recurrenceRule' => 'nullable|string|max:100',
            'recurrenceEnd' => 'nullable|date',
        ]);

        // Validate endAt >= startAt when both are present
        $startAt = $request->input('startAt');
        $endAt = $request->input('endAt');
        if ($startAt && $endAt && strtotime($endAt) < strtotime($startAt)) {
            return response()->json(['message' => 'End date must be after or equal to start date.'], 422);
        }

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
        if ($request->has('recurrenceEnd')) {
            $data['recurrence_end'] = $request->input('recurrenceEnd');
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

    public function export(Request $request)
    {
        $rangeStart = $request->has('start') ? Carbon::parse($request->input('start')) : null;
        $rangeEnd = $request->has('end') ? Carbon::parse($request->input('end')) : null;

        // Non-recurring events
        $query = CalendarEvent::with(['attendees.user'])->whereNull('recurrence_rule');

        if ($rangeStart) {
            $query->where('start_at', '>=', $rangeStart);
        }
        if ($rangeEnd) {
            $query->where('start_at', '<=', $rangeEnd);
        }

        $events = $query->orderBy('start_at')->get();

        // Expand recurring events if range is provided
        if ($rangeStart && $rangeEnd) {
            $recurringQuery = CalendarEvent::with(['attendees.user'])
                ->whereNotNull('recurrence_rule')
                ->where('start_at', '<=', $rangeEnd)
                ->where(function ($q) use ($rangeStart) {
                    $q->whereNull('recurrence_end')
                        ->orWhere('recurrence_end', '>=', $rangeStart);
                });

            $expanded = $this->expandRecurringEvents($recurringQuery->get(), $rangeStart, $rangeEnd);
            $events = $events->concat($expanded)->sortBy('start_at');
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OpenCompany//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($events as $event) {
            $isInstance = $event->recurrence_rule !== null;
            $uid = $isInstance
                ? $event->id . '-' . Carbon::parse($event->start_at)->format('Ymd\THis') . '@opencompany'
                : $event->id . '@opencompany';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;

            if ($event->all_day) {
                $lines[] = 'DTSTART;VALUE=DATE:' . Carbon::parse($event->start_at)->format('Ymd');
                if ($event->end_at) {
                    // ICS all-day end is exclusive, so add 1 day
                    $lines[] = 'DTEND;VALUE=DATE:' . Carbon::parse($event->end_at)->addDay()->format('Ymd');
                }
            } else {
                $lines[] = 'DTSTART:' . Carbon::parse($event->start_at)->utc()->format('Ymd\THis\Z');
                if ($event->end_at) {
                    $lines[] = 'DTEND:' . Carbon::parse($event->end_at)->utc()->format('Ymd\THis\Z');
                }
            }

            $lines[] = 'SUMMARY:' . $this->escapeIcsText($event->title);

            if ($event->description) {
                $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($event->description);
            }
            if ($event->location) {
                $lines[] = 'LOCATION:' . $this->escapeIcsText($event->location);
            }

            foreach ($event->attendees as $attendee) {
                $name = $attendee->user?->name ?? 'Unknown';
                $lines[] = 'ATTENDEE;CN=' . $this->escapeIcsText($name) . ':mailto:' . ($attendee->user?->email ?? 'noreply@opencompany');
            }

            $lines[] = 'CREATED:' . Carbon::parse($event->created_at)->utc()->format('Ymd\THis\Z');
            $lines[] = 'LAST-MODIFIED:' . Carbon::parse($event->updated_at)->utc()->format('Ymd\THis\Z');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="calendar.ics"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:ics,txt|max:2048',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        return $this->importIcsContent($content);
    }

    public function importFromUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $response = Http::timeout(15)->get($request->input('url'));

        if (!$response->successful()) {
            return response()->json(['message' => 'Failed to fetch ICS from the provided URL.'], 422);
        }

        return $this->importIcsContent($response->body());
    }

    private function importIcsContent(string $content)
    {
        // Unfold lines (RFC 5545: lines starting with space/tab are continuations)
        $content = preg_replace('/\r?\n[ \t]/', '', $content);

        $events = [];
        $currentEvent = null;

        foreach (explode("\n", str_replace("\r\n", "\n", $content)) as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $currentEvent = [];
                continue;
            }

            if ($line === 'END:VEVENT' && $currentEvent !== null) {
                $events[] = $currentEvent;
                $currentEvent = null;
                continue;
            }

            if ($currentEvent === null) {
                continue;
            }

            // Parse property:value (handle properties with parameters like DTSTART;VALUE=DATE:20260207)
            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $property = substr($line, 0, $colonPos);
            $value = substr($line, $colonPos + 1);

            // Strip parameters from property name (e.g. DTSTART;VALUE=DATE -> DTSTART)
            $propertyName = strtoupper(explode(';', $property)[0]);
            $params = strtoupper($property);

            $currentEvent[$propertyName] = [
                'value' => $value,
                'params' => $params,
            ];
        }

        $created = [];
        $userId = auth()->id();

        foreach ($events as $eventData) {
            $title = $this->unescapeIcsText($eventData['SUMMARY']['value'] ?? '');
            if (empty($title)) {
                continue;
            }

            $startRaw = $eventData['DTSTART']['value'] ?? null;
            $endRaw = $eventData['DTEND']['value'] ?? null;
            $startParams = $eventData['DTSTART']['params'] ?? '';

            $allDay = str_contains($startParams, 'VALUE=DATE');
            $startAt = $this->parseIcsDate($startRaw, $allDay);

            if (!$startAt) {
                continue;
            }

            $endAt = $endRaw ? $this->parseIcsDate($endRaw, $allDay) : null;

            // For all-day events, ICS end date is exclusive — subtract 1 day
            if ($allDay && $endAt) {
                $endAt = Carbon::parse($endAt)->subDay()->toDateString();
            }

            $event = CalendarEvent::create([
                'title' => $title,
                'description' => $this->unescapeIcsText($eventData['DESCRIPTION']['value'] ?? ''),
                'start_at' => $startAt,
                'end_at' => $endAt,
                'all_day' => $allDay,
                'location' => $this->unescapeIcsText($eventData['LOCATION']['value'] ?? ''),
                'created_by' => $userId,
            ]);

            $created[] = $event;
        }

        return response()->json([
            'imported' => count($created),
            'events' => collect($created)->map->load(['creator', 'attendees.user']),
        ]);
    }

    /**
     * Expand recurring events into virtual instances within the given range.
     */
    private function expandRecurringEvents($recurringEvents, Carbon $rangeStart, Carbon $rangeEnd): \Illuminate\Support\Collection
    {
        $expanded = collect();

        foreach ($recurringEvents as $event) {
            try {
                $cron = new CronExpression($event->recurrence_rule);
            } catch (\Throwable) {
                continue;
            }

            $duration = $event->end_at && $event->start_at
                ? $event->start_at->diffInSeconds($event->end_at)
                : null;

            $effectiveEnd = $event->recurrence_end
                ? min($rangeEnd, Carbon::parse($event->recurrence_end))
                : $rangeEnd;

            $effectiveStart = max($rangeStart, $event->start_at);

            // Iterate forward from effectiveStart, collecting run dates until effectiveEnd
            $cursor = $effectiveStart->copy()->subMinute();
            $limit = 366;

            while ($limit-- > 0) {
                try {
                    $nextDate = Carbon::instance($cron->getNextRunDate($cursor->toDateTime()));
                } catch (\Throwable) {
                    break;
                }

                if ($nextDate->gt($effectiveEnd)) {
                    break;
                }

                $instance = $event->replicate();
                $instance->id = $event->id;
                $instance->start_at = $nextDate;
                if ($duration !== null) {
                    $instance->end_at = $nextDate->copy()->addSeconds($duration);
                }
                $instance->setAttribute('is_recurrence_instance', true);
                $instance->setAttribute('original_event_id', $event->id);
                $instance->setRelations($event->getRelations());

                $expanded->push($instance);

                $cursor = $nextDate->copy()->addMinute();
            }
        }

        return $expanded;
    }

    private function escapeIcsText(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $text
        );
    }

    private function unescapeIcsText(string $text): string
    {
        return str_replace(
            ['\\n', '\\,', '\\;', '\\\\'],
            ["\n", ',', ';', '\\'],
            $text
        );
    }

    private function parseIcsDate(?string $value, bool $allDay): ?string
    {
        if (!$value) {
            return null;
        }

        // Date only: 20260207
        if ($allDay && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // DateTime with Z (UTC): 20260207T230000Z
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/', $value, $m)) {
            $carbon = Carbon::createFromFormat('Y-m-d H:i:s', "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}", 'UTC');
            return $carbon->toIso8601String();
        }

        return null;
    }
}
