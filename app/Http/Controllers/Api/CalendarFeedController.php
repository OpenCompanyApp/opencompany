<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class CalendarFeedController extends Controller
{
    /**
     * @return Collection<int, mixed>
     */
    public function index(): Collection
    {
        return CalendarFeed::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CalendarFeed $feed) {
                return [
                    'id' => $feed->id,
                    'name' => $feed->name,
                    'token' => $feed->token,
                    'url' => url("/cal/{$feed->token}.ics"),
                    'createdAt' => $feed->created_at,
                ];
            });
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $feed = CalendarFeed::create([
            'user_id' => auth()->id(),
            'name' => $request->input('name', 'My Calendar'),
        ]);

        return response()->json([
            'id' => $feed->id,
            'name' => $feed->name,
            'token' => $feed->token,
            'url' => url("/cal/{$feed->token}.ics"),
            'createdAt' => $feed->created_at,
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $feed = CalendarFeed::where('user_id', auth()->id())->findOrFail($id);
        $feed->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Public ICS feed endpoint — no auth required, uses token.
     */
    public function feed(string $token): Response
    {
        $feed = CalendarFeed::where('token', $token)->firstOrFail();
        $userId = $feed->user_id;

        $rangeStart = now()->subDays(30);
        $rangeEnd = now()->addYear();

        // Get non-recurring events for this user
        $query = CalendarEvent::with(['attendees.user'])
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('attendees', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
            })
            ->whereNull('recurrence_rule')
            ->where('start_at', '>=', $rangeStart)
            ->where('start_at', '<=', $rangeEnd);

        $events = $query->orderBy('start_at')->get();

        // Get recurring events and expand them
        $recurringEvents = CalendarEvent::with(['attendees.user'])
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('attendees', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
            })
            ->whereNotNull('recurrence_rule')
            ->where('start_at', '<=', $rangeEnd)
            ->where(function ($q) use ($rangeStart) {
                $q->whereNull('recurrence_end')
                    ->orWhere('recurrence_end', '>=', $rangeStart);
            })
            ->get();

        $expandedEvents = $this->expandRecurringEvents($recurringEvents, $rangeStart, $rangeEnd);

        $allEvents = $events->concat($expandedEvents)->sortBy('start_at');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OpenCompany//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcsText($feed->name ?? 'OpenCompany'),
        ];

        foreach ($allEvents as $event) {
            $lines = array_merge($lines, $this->eventToVevent($event));
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, CalendarEvent> $recurringEvents
     * @return Collection<int, CalendarEvent>
     */
    private function expandRecurringEvents(\Illuminate\Database\Eloquent\Collection $recurringEvents, Carbon $rangeStart, Carbon $rangeEnd): Collection
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

                $instance = clone $event;
                $instance->start_at = $nextDate;
                if ($duration !== null) {
                    $instance->end_at = $nextDate->copy()->addSeconds($duration);
                }

                $expanded->push($instance);

                $cursor = $nextDate->copy()->addMinute();
            }
        }

        return $expanded;
    }

    /**
     * @return array<int, string>
     */
    private function eventToVevent(CalendarEvent $event): array
    {
        $isInstance = $event->recurrence_rule !== null;
        $uid = $isInstance
            ? $event->id . '-' . $event->start_at->format('Ymd\THis') . '@opencompany'
            : $event->id . '@opencompany';

        $lines = ['BEGIN:VEVENT', 'UID:' . $uid];

        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $event->start_at->format('Ymd');
            if ($event->end_at) {
                $lines[] = 'DTEND;VALUE=DATE:' . $event->end_at->copy()->addDay()->format('Ymd');
            }
        } else {
            $lines[] = 'DTSTART:' . $event->start_at->utc()->format('Ymd\THis\Z');
            if ($event->end_at) {
                $lines[] = 'DTEND:' . $event->end_at->utc()->format('Ymd\THis\Z');
            }
        }

        $lines[] = 'SUMMARY:' . $this->escapeIcsText($event->title);

        if ($event->description) {
            $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($event->description);
        }
        if ($event->location) {
            $lines[] = 'LOCATION:' . $this->escapeIcsText($event->location);
        }

        if ($event->relationLoaded('attendees')) {
            foreach ($event->attendees as $attendee) {
                $name = $attendee->user->name ?? 'Unknown';
                $lines[] = 'ATTENDEE;CN=' . $this->escapeIcsText($name) . ':mailto:' . ($attendee->user->email ?? 'noreply@opencompany');
            }
        }

        $lines[] = 'CREATED:' . Carbon::parse($event->created_at)->utc()->format('Ymd\THis\Z');
        $lines[] = 'LAST-MODIFIED:' . Carbon::parse($event->updated_at)->utc()->format('Ymd\THis\Z');
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function escapeIcsText(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $text
        );
    }
}
