<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\Calendar\GetCalendarEvent;
use App\Agents\Tools\Calendar\ListCalendarEvents;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class QueryCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeListTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new ListCalendarEvents($agent);

        return [$tool, $agent];
    }

    private function makeGetTool(?User $agent = null): array
    {
        $agent = $agent ?? User::factory()->create(['type' => 'agent']);
        $tool = new GetCalendarEvent($agent);

        return [$tool, $agent];
    }

    public function test_lists_events(): void
    {
        [$tool, $agent] = $this->makeListTool();

        CalendarEvent::create([
            'title' => 'Team Standup',
            'start_at' => '2026-02-10 09:00:00',
            'end_at' => '2026-02-10 09:30:00',
            'all_day' => false,
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $request = new Request([]);
        $result = $tool->handle($request);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertEquals('Team Standup', $decoded[0]['title']);
    }

    public function test_gets_event_details(): void
    {
        [$tool, $agent] = $this->makeGetTool();

        $event = CalendarEvent::create([
            'title' => 'Sprint Planning',
            'description' => 'Plan the next sprint',
            'start_at' => '2026-02-12 14:00:00',
            'end_at' => '2026-02-12 15:00:00',
            'all_day' => false,
            'location' => 'Conference Room A',
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $attendee = User::factory()->create(['name' => 'Alice Tester']);
        CalendarEventAttendee::create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'status' => 'accepted',
        ]);

        $request = new Request(['eventId' => $event->id]);
        $result = $tool->handle($request);

        $decoded = json_decode($result, true);
        $this->assertEquals('Sprint Planning', $decoded['title']);
        $this->assertEquals('Plan the next sprint', $decoded['description']);
        $this->assertEquals('Conference Room A', $decoded['location']);
        $this->assertCount(1, $decoded['attendees']);
        $this->assertEquals('Alice Tester', $decoded['attendees'][0]['name']);
        $this->assertEquals('accepted', $decoded['attendees'][0]['status']);
    }

    public function test_filters_by_date_range(): void
    {
        [$tool, $agent] = $this->makeListTool();

        CalendarEvent::create([
            'title' => 'Early Meeting',
            'start_at' => '2026-02-01 09:00:00',
            'end_at' => '2026-02-01 10:00:00',
            'all_day' => false,
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        CalendarEvent::create([
            'title' => 'Late Meeting',
            'start_at' => '2026-03-15 09:00:00',
            'end_at' => '2026-03-15 10:00:00',
            'all_day' => false,
            'created_by' => $agent->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $request = new Request([
            'startDate' => '2026-03-01',
            'endDate' => '2026-03-31',
        ]);
        $result = $tool->handle($request);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertEquals('Late Meeting', $decoded[0]['title']);
    }

    public function test_returns_empty_when_no_events(): void
    {
        [$tool] = $this->makeListTool();

        $request = new Request([]);
        $result = $tool->handle($request);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded);
    }

    public function test_list_has_correct_description(): void
    {
        [$tool] = $this->makeListTool();

        $this->assertStringContainsString('List calendar events', $tool->description());
    }

    public function test_get_has_correct_description(): void
    {
        [$tool] = $this->makeGetTool();

        $this->assertStringContainsString('Get detailed information', $tool->description());
    }
}
