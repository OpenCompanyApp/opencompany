<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Calendar\CreateCalendarEvent;
use App\Agents\Tools\Calendar\DeleteCalendarEvent;
use App\Agents\Tools\Calendar\GetCalendarEvent;
use App\Agents\Tools\Calendar\ListCalendarEvents;
use App\Agents\Tools\Calendar\RemoveCalendarAttendee;
use App\Agents\Tools\Calendar\UpdateCalendarAttendee;
use App\Agents\Tools\Calendar\UpdateCalendarEvent;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers calendar and attendee management tools.
 *
 * The provider keeps event CRUD and RSVP updates in one group so tool catalogs,
 * Lua docs, and permission surfaces describe calendar work consistently.
 */
class CalendarToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'calendar';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list, get, create, update, delete, attendees',
            'description' => 'Events and scheduling',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:calendar';
    }

    public function tools(): array
    {
        return [
            'list_calendar_events' => [
                'class' => ListCalendarEvents::class,
                'type' => 'read',
                'name' => 'List Calendar Events',
                'description' => 'List events by date range, optionally filtered by user.',
                'icon' => 'ph:calendar',
            ],
            'get_calendar_event' => [
                'class' => GetCalendarEvent::class,
                'type' => 'read',
                'name' => 'Get Calendar Event',
                'description' => 'Get detailed information about a specific calendar event.',
                'icon' => 'ph:calendar',
            ],
            'create_calendar_event' => [
                'class' => CreateCalendarEvent::class,
                'type' => 'write',
                'name' => 'Create Calendar Event',
                'description' => 'Create a new calendar event with attendees.',
                'icon' => 'ph:calendar-plus',
            ],
            'update_calendar_event' => [
                'class' => UpdateCalendarEvent::class,
                'type' => 'write',
                'name' => 'Update Calendar Event',
                'description' => 'Update an existing calendar event.',
                'icon' => 'ph:calendar-plus',
            ],
            'delete_calendar_event' => [
                'class' => DeleteCalendarEvent::class,
                'type' => 'write',
                'name' => 'Delete Calendar Event',
                'description' => 'Delete a calendar event.',
                'icon' => 'ph:calendar-minus',
            ],
            'update_calendar_attendee' => [
                'class' => UpdateCalendarAttendee::class,
                'type' => 'write',
                'name' => 'Update Calendar Attendee',
                'description' => 'Update an attendee\'s RSVP status on a calendar event.',
                'icon' => 'ph:user-check',
            ],
            'remove_calendar_attendee' => [
                'class' => RemoveCalendarAttendee::class,
                'type' => 'write',
                'name' => 'Remove Calendar Attendee',
                'description' => 'Remove an attendee from a calendar event.',
                'icon' => 'ph:user-minus',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // Calendar tools use the agent for workspace scoping and author/audit
        // attribution; each tool validates the event-specific payload itself.
        return new $class($agent);
    }
}
