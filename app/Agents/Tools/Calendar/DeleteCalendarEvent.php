<?php

namespace App\Agents\Tools\Calendar;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteCalendarEvent implements Tool
{
    public function __construct(private User $agent) {}

    public function description(): string
    {
        return 'Delete a calendar event by its ID.';
    }

    public function handle(Request $request): string
    {
        try {
            $event = CalendarEvent::forWorkspace()->findOrFail($request['eventId']);
            $title = $event->title;
            $event->delete();

            return "Event '{$title}' deleted.";
        } catch (\Throwable $e) {
            return "Error deleting calendar event: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'eventId' => $schema
                ->string()
                ->description('The UUID of the event to delete.')
                ->required(),
        ];
    }
}
