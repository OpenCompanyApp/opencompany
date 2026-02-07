<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Calendar\QueryCalendar;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class QueryCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new QueryCalendar($agent);

        return [$tool, $agent];
    }

    public function test_lists_events(): void
    {
        [$tool, $agent] = $this->makeTool();

        CalendarEvent::create([
            'title' => 'Team Standup',
            'start_at' => '2026-02-10 09:00:00',
            'end_at' => '2026-02-10 09:30:00',
            'all_day' => false,
            'created_by' => $agent->id,
        ]);

        $request = new Request(['action' => 'list_events']);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Team Standup', $result);
        $this->assertStringContainsString('Calendar events (1)', $result);
    }

    public function test_gets_event_details(): void
    {
        [$tool, $agent] = $this->makeTool();

        $event = CalendarEvent::create([
            'title' => 'Sprint Planning',
            'description' => 'Plan the next sprint',
            'start_at' => '2026-02-12 14:00:00',
            'end_at' => '2026-02-12 15:00:00',
            'all_day' => false,
            'location' => 'Conference Room A',
            'created_by' => $agent->id,
        ]);

        $attendee = User::factory()->create(['name' => 'Alice Tester']);
        CalendarEventAttendee::create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'status' => 'accepted',
        ]);

        $request = new Request(['action' => 'get_event', 'eventId' => $event->id]);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Event: Sprint Planning', $result);
        $this->assertStringContainsString('Plan the next sprint', $result);
        $this->assertStringContainsString('Conference Room A', $result);
        $this->assertStringContainsString('Alice Tester', $result);
        $this->assertStringContainsString('accepted', $result);
    }

    public function test_filters_by_date_range(): void
    {
        [$tool, $agent] = $this->makeTool();

        CalendarEvent::create([
            'title' => 'Early Meeting',
            'start_at' => '2026-02-01 09:00:00',
            'end_at' => '2026-02-01 10:00:00',
            'all_day' => false,
            'created_by' => $agent->id,
        ]);

        CalendarEvent::create([
            'title' => 'Late Meeting',
            'start_at' => '2026-03-15 09:00:00',
            'end_at' => '2026-03-15 10:00:00',
            'all_day' => false,
            'created_by' => $agent->id,
        ]);

        $request = new Request([
            'action' => 'list_events',
            'startDate' => '2026-03-01',
            'endDate' => '2026-03-31',
        ]);
        $result = $tool->handle($request);

        $this->assertStringContainsString('Late Meeting', $result);
        $this->assertStringNotContainsString('Early Meeting', $result);
    }

    public function test_returns_empty_when_no_events(): void
    {
        [$tool] = $this->makeTool();

        $request = new Request(['action' => 'list_events']);
        $result = $tool->handle($request);

        $this->assertStringContainsString('No calendar events found', $result);
    }

    public function test_has_correct_description(): void
    {
        [$tool] = $this->makeTool();

        $this->assertStringContainsString('Query calendar events', $tool->description());
    }
}
