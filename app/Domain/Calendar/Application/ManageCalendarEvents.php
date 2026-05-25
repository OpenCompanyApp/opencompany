<?php

namespace App\Domain\Calendar\Application;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Application service for workspace calendar event use cases.
 *
 * The HTTP controller owns request validation and transport concerns. This
 * service owns event persistence, workspace scoping, recurrence expansion, and
 * the small ICS codec used by OpenCompany calendar import/export endpoints.
 */
class ManageCalendarEvents
{
    /**
     * Return calendar events visible in the optional date/user range.
     *
     * Recurring events are expanded into virtual CalendarEvent instances only
     * when both range bounds are present, matching the existing API contract.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function list(
        Workspace $workspace,
        ?Carbon $rangeStart = null,
        ?Carbon $rangeEnd = null,
        ?string $userId = null,
    ): Collection {
        $events = $this->baseQuery($workspace, $userId)
            ->whereNull('recurrence_rule')
            ->when($rangeStart, fn (Builder $query) => $query->where('start_at', '>=', $rangeStart))
            ->when($rangeEnd, fn (Builder $query) => $query->where('start_at', '<=', $rangeEnd))
            ->orderBy('start_at')
            ->get();

        if (! $rangeStart || ! $rangeEnd) {
            return $events;
        }

        $recurringEvents = $this->baseQuery($workspace, $userId)
            ->whereNotNull('recurrence_rule')
            ->where('start_at', '<=', $rangeEnd)
            ->where(function (Builder $query) use ($rangeStart) {
                $query->whereNull('recurrence_end')
                    ->orWhere('recurrence_end', '>=', $rangeStart);
            })
            ->get();

        return $events
            ->concat($this->expandRecurringEvents($recurringEvents, $rangeStart, $rangeEnd))
            ->sortBy('start_at')
            ->values();
    }

    public function show(Workspace $workspace, string $id): CalendarEvent
    {
        return CalendarEvent::forWorkspace($workspace)
            ->with(['creator', 'attendees.user'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(Workspace $workspace, User $creator, array $input): CalendarEvent
    {
        $event = CalendarEvent::create([
            'workspace_id' => $workspace->id,
            'title' => $input['title'],
            'description' => $input['description'] ?? null,
            'start_at' => $input['startAt'],
            'end_at' => $input['endAt'] ?? null,
            'all_day' => $input['allDay'] ?? false,
            'location' => $input['location'] ?? null,
            'color' => $input['color'] ?? null,
            'recurrence_rule' => $input['recurrenceRule'] ?? null,
            'recurrence_end' => $input['recurrenceEnd'] ?? null,
            'created_by' => $creator->id,
        ]);

        if (array_key_exists('attendeeIds', $input)) {
            $this->syncAttendees($event, $input['attendeeIds'] ?? []);
        }

        return $event->load(['creator', 'attendees.user']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Workspace $workspace, string $id, array $input): CalendarEvent
    {
        $event = CalendarEvent::forWorkspace($workspace)->findOrFail($id);
        $data = [];

        foreach ([
            'title' => 'title',
            'description' => 'description',
            'startAt' => 'start_at',
            'endAt' => 'end_at',
            'allDay' => 'all_day',
            'location' => 'location',
            'color' => 'color',
            'recurrenceRule' => 'recurrence_rule',
            'recurrenceEnd' => 'recurrence_end',
        ] as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $data[$column] = $input[$inputKey];
            }
        }

        $event->update($data);

        if (array_key_exists('attendeeIds', $input)) {
            $this->syncAttendees($event, $input['attendeeIds'] ?? []);
        }

        return $event->load(['creator', 'attendees.user']);
    }

    public function delete(Workspace $workspace, string $id): void
    {
        CalendarEvent::forWorkspace($workspace)->findOrFail($id)->delete();
    }

    public function exportIcs(Workspace $workspace, ?Carbon $rangeStart = null, ?Carbon $rangeEnd = null): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OpenCompany//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($this->list($workspace, $rangeStart, $rangeEnd) as $event) {
            $lines = array_merge($lines, $this->eventToIcsLines($event));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Import VEVENT blocks from a small RFC 5545 subset.
     *
     * This parser intentionally supports the fields OpenCompany exports and
     * common third-party feeds. It ignores malformed or unsupported VEVENTs
     * instead of partially creating invalid calendar rows.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function importIcsContent(Workspace $workspace, User $creator, string $content): Collection
    {
        $created = collect();

        foreach ($this->parseIcsEvents($content) as $eventData) {
            $title = $this->unescapeIcsText($eventData['SUMMARY']['value'] ?? '');
            if ($title === '') {
                continue;
            }

            $startRaw = $eventData['DTSTART']['value'] ?? null;
            $endRaw = $eventData['DTEND']['value'] ?? null;
            $startParams = $eventData['DTSTART']['params'] ?? '';

            $allDay = str_contains($startParams, 'VALUE=DATE');
            $startAt = $this->parseIcsDate($startRaw, $allDay);
            if (! $startAt) {
                continue;
            }

            $endAt = $endRaw ? $this->parseIcsDate($endRaw, $allDay) : null;
            if ($allDay && $endAt) {
                // ICS all-day DTEND is exclusive; OpenCompany stores inclusive.
                $endAt = Carbon::parse($endAt)->subDay()->toDateString();
            }

            $created->push(CalendarEvent::create([
                'workspace_id' => $workspace->id,
                'title' => $title,
                'description' => $this->unescapeIcsText($eventData['DESCRIPTION']['value'] ?? ''),
                'start_at' => $startAt,
                'end_at' => $endAt,
                'all_day' => $allDay,
                'location' => $this->unescapeIcsText($eventData['LOCATION']['value'] ?? ''),
                'created_by' => $creator->id,
            ])->load(['creator', 'attendees.user']));
        }

        return $created;
    }

    private function baseQuery(Workspace $workspace, ?string $userId = null): Builder
    {
        return CalendarEvent::forWorkspace($workspace)
            ->with(['creator', 'attendees.user'])
            ->when($userId, function (Builder $query) use ($userId) {
                $query->where(function (Builder $query) use ($userId) {
                    $query->where('created_by', $userId)
                        ->orWhereHas('attendees', fn (Builder $query) => $query->where('user_id', $userId));
                });
            });
    }

    /**
     * @param  array<int, string>  $attendeeIds
     */
    private function syncAttendees(CalendarEvent $event, array $attendeeIds): void
    {
        $event->attendees()->delete();

        foreach ($attendeeIds as $userId) {
            $event->attendees()->create([
                'user_id' => $userId,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Expand recurring events into virtual instances within the given range.
     *
     * @param  EloquentCollection<int, CalendarEvent>  $recurringEvents
     * @return Collection<int, CalendarEvent>
     */
    private function expandRecurringEvents(EloquentCollection $recurringEvents, Carbon $rangeStart, Carbon $rangeEnd): Collection
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

    /**
     * @return list<string>
     */
    private function eventToIcsLines(CalendarEvent $event): array
    {
        $isInstance = $event->recurrence_rule !== null;
        $uid = $isInstance
            ? $event->id.'-'.Carbon::parse($event->start_at)->format('Ymd\THis').'@opencompany'
            : $event->id.'@opencompany';

        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$uid,
        ];

        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:'.Carbon::parse($event->start_at)->format('Ymd');
            if ($event->end_at) {
                $lines[] = 'DTEND;VALUE=DATE:'.Carbon::parse($event->end_at)->addDay()->format('Ymd');
            }
        } else {
            $lines[] = 'DTSTART:'.Carbon::parse($event->start_at)->utc()->format('Ymd\THis\Z');
            if ($event->end_at) {
                $lines[] = 'DTEND:'.Carbon::parse($event->end_at)->utc()->format('Ymd\THis\Z');
            }
        }

        $lines[] = 'SUMMARY:'.$this->escapeIcsText($event->title);

        if ($event->description) {
            $lines[] = 'DESCRIPTION:'.$this->escapeIcsText($event->description);
        }
        if ($event->location) {
            $lines[] = 'LOCATION:'.$this->escapeIcsText($event->location);
        }

        foreach ($event->attendees as $attendee) {
            $name = $attendee->user->name ?? 'Unknown';
            $email = $attendee->user->email ?? 'noreply@opencompany';
            $lines[] = 'ATTENDEE;CN='.$this->escapeIcsText($name).':mailto:'.$email;
        }

        $lines[] = 'CREATED:'.Carbon::parse($event->created_at)->utc()->format('Ymd\THis\Z');
        $lines[] = 'LAST-MODIFIED:'.Carbon::parse($event->updated_at)->utc()->format('Ymd\THis\Z');
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * @return list<array<string, array{value: string, params: string}>>
     */
    private function parseIcsEvents(string $content): array
    {
        $content = preg_replace('/\r?\n[ \t]/', '', $content) ?? $content;

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

            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $property = substr($line, 0, $colonPos);
            $propertyName = strtoupper(explode(';', $property)[0]);

            $currentEvent[$propertyName] = [
                'value' => substr($line, $colonPos + 1),
                'params' => strtoupper($property),
            ];
        }

        return $events;
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
        if (! $value) {
            return null;
        }

        if ($allDay && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/', $value, $matches)) {
            return Carbon::createFromFormat(
                'Y-m-d H:i:s',
                "{$matches[1]}-{$matches[2]}-{$matches[3]} {$matches[4]}:{$matches[5]}:{$matches[6]}",
                'UTC'
            )->toIso8601String();
        }

        return null;
    }
}
