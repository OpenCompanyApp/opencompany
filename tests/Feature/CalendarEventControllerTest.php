<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_endpoints_delegate_to_domain_service_without_changing_api_shape(): void
    {
        $user = User::factory()->create(['type' => 'human']);

        $create = $this->actingAs($user)->postJson('/api/calendar/events', [
            'title' => 'API planning',
            'startAt' => '2026-05-19 09:00:00',
            'endAt' => '2026-05-19 10:00:00',
            'allDay' => false,
            'location' => 'Remote',
        ]);

        $create->assertCreated()
            ->assertJsonPath('title', 'API planning')
            ->assertJsonPath('allDay', false)
            ->assertJsonPath('createdBy', $user->id);

        $eventId = $create->json('id');

        $this->actingAs($user)
            ->getJson('/api/calendar/events?start=2026-05-19&end=2026-05-20')
            ->assertOk()
            ->assertJsonFragment(['id' => $eventId]);

        $this->actingAs($user)
            ->get('/api/calendar/events/export.ics?start=2026-05-19&end=2026-05-20')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:API planning', false);
    }

    public function test_calendar_routes_remain_workspace_scoped(): void
    {
        $user = User::factory()->create(['type' => 'human']);
        $event = CalendarEvent::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Own event',
            'start_at' => '2026-05-19 09:00:00',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson("/api/calendar/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Own event');
    }
}
