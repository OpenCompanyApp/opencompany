<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListCalendarEvents implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return "List calendar events within a date range, optionally filtered by user.";
    }

    public function handle(Request $request): string
    {
        try {
            $startDate = $request["startDate"] ?? null;
            $endDate = $request["endDate"] ?? null;
            $userId = $request["userId"] ?? null;

            $query = CalendarEvent::forWorkspace()->with(["creator", "attendees.user"]);

            if ($startDate) {
                $query->where("start_at", ">=", $startDate);
            }

            if ($endDate) {
                $query->where("start_at", "<=", $endDate);
            }

            if ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where("created_by", $userId)
                        ->orWhereHas("attendees", function ($aq) use ($userId) {
                            $aq->where("user_id", $userId);
                        });
                });
            }

            $events = $query->orderBy("start_at", "asc")->get();

            if ($events->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($events->map(fn ($event) => array_filter([
                'id' => $event->id,
                'title' => $event->title,
                'startAt' => $event->start_at->toIso8601String(),
                'endAt' => $event->end_at?->toIso8601String(),
                'allDay' => $event->all_day ?: null,
                'location' => $event->location,
                'recurrenceRule' => $event->recurrence_rule,
                'attendees' => $event->attendees->count(),
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing calendar events: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            "startDate" => $schema
                ->string()
                ->description("Filter events starting from this date (ISO 8601 format)."),
            "endDate" => $schema
                ->string()
                ->description("Filter events up to this date (ISO 8601 format)."),
            "userId" => $schema
                ->string()
                ->description("Filter events by user (as creator or attendee)."),
        ];
    }
}
