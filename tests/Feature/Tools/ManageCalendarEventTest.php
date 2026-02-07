<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Calendar\ManageCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ManageCalendarEventTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new ManageCalendarEvent($agent);

        return [$tool, $agent];
    }

    public function test_creates_event(): void
    {
        [$tool, $agent] = $this->makeTool();

        $request = new Request([
            'action' => 'create',
            'title' => 'Weekly Sync',
            'startAt' => '2026-02-10T10:00:00Z',
            'endAt' => '2026-02-10T11:00:00Z',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Event created', $result);
        $this->assertStringContainsString('Weekly Sync', $result);

        $event = CalendarEvent::where('title', 'Weekly Sync')->first();
        $this->assertNotNull($event);
        $this->assertEquals($agent->id, $event->created_by);
    }

    public function test_creates_event_with_attendees(): void
    {
        [$tool, $agent] = $this->makeTool();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $request = new Request([
            'action' => 'create',
            'title' => 'Team Retro',
            'startAt' => '2026-02-11T14:00:00Z',
            'attendeeIds' => "{$user1->id},{$user2->id}",
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Event created', $result);

        $event = CalendarEvent::where('title', 'Team Retro')->first();
        $this->assertNotNull($event);
        $this->assertCount(2, $event->attendees);

        $attendeeUserIds = $event->attendees->pluck('user_id')->sort()->values()->toArray();
        $expectedIds = collect([$user1->id, $user2->id])->sort()->values()->toArray();
        $this->assertEquals($expectedIds, $attendeeUserIds);
    }

    public function test_updates_event(): void
    {
        [$tool, $agent] = $this->makeTool();

        $event = CalendarEvent::create([
            'title' => 'Old Title',
            'start_at' => '2026-02-10 09:00:00',
            'end_at' => '2026-02-10 10:00:00',
            'all_day' => false,
            'created_by' => $agent->id,
        ]);

        $request = new Request([
            'action' => 'update',
            'eventId' => $event->id,
            'title' => 'Updated Title',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('updated', $result);

        $event->refresh();
        $this->assertEquals('Updated Title', $event->title);
    }

    public function test_deletes_event(): void
    {
        [$tool, $agent] = $this->makeTool();

        $event = CalendarEvent::create([
            'title' => 'To Be Deleted',
            'start_at' => '2026-02-10 09:00:00',
            'end_at' => '2026-02-10 10:00:00',
            'all_day' => false,
            'created_by' => $agent->id,
        ]);

        $eventId = $event->id;

        $request = new Request([
            'action' => 'delete',
            'eventId' => $eventId,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('deleted', $result);
        $this->assertStringContainsString('To Be Deleted', $result);
        $this->assertNull(CalendarEvent::find($eventId));
    }

    public function test_has_correct_description(): void
    {
        [$tool] = $this->makeTool();

        $this->assertStringContainsString('Create, update, or delete calendar events', $tool->description());
    }
}
