<?php

namespace Tests\Feature\Domain\Calendar;

use App\Domain\Calendar\Application\ManageCalendarEvents;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageCalendarEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_update_and_export_keep_calendar_logic_outside_controller(): void
    {
        $owner = User::factory()->create(['type' => 'human']);
        $attendee = User::factory()->create(['type' => 'human']);
        $calendar = app(ManageCalendarEvents::class);

        $event = $calendar->create($this->workspace, $owner, [
            'title' => 'Planning sync',
            'description' => 'Quarterly planning',
            'startAt' => '2026-05-19 09:00:00',
            'endAt' => '2026-05-19 10:00:00',
            'location' => 'HQ',
            'attendeeIds' => [$attendee->id],
        ]);

        $updated = $calendar->update($this->workspace, $event->id, [
            'title' => 'Planning sync updated',
            'attendeeIds' => [],
        ]);

        $ics = $calendar->exportIcs(
            $this->workspace,
            Carbon::parse('2026-05-19 00:00:00'),
            Carbon::parse('2026-05-20 00:00:00'),
        );

        $this->assertSame('Planning sync updated', $updated->title);
        $this->assertCount(0, $updated->attendees);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('SUMMARY:Planning sync updated', $ics);
        $this->assertStringContainsString('LOCATION:HQ', $ics);
    }

    public function test_list_expands_recurring_events_and_respects_workspace_scope(): void
    {
        $owner = User::factory()->create(['type' => 'human']);
        $otherWorkspace = Workspace::create([
            'name' => 'Other Workspace',
            'slug' => 'calendar-other',
        ]);

        CalendarEvent::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Daily standup',
            'start_at' => '2026-05-19 09:00:00',
            'end_at' => '2026-05-19 09:30:00',
            'recurrence_rule' => '0 9 * * *',
            'created_by' => $owner->id,
        ]);
        CalendarEvent::create([
            'workspace_id' => $otherWorkspace->id,
            'title' => 'Foreign standup',
            'start_at' => '2026-05-19 09:00:00',
            'end_at' => '2026-05-19 09:30:00',
            'recurrence_rule' => '0 9 * * *',
            'created_by' => $owner->id,
        ]);

        $events = app(ManageCalendarEvents::class)->list(
            $this->workspace,
            Carbon::parse('2026-05-19 00:00:00'),
            Carbon::parse('2026-05-21 00:00:00'),
        );

        $this->assertCount(2, $events);
        $this->assertSame(['Daily standup', 'Daily standup'], $events->pluck('title')->all());
        $this->assertTrue($events->every(fn (CalendarEvent $event) => $event->getAttribute('is_recurrence_instance')));
    }

    public function test_import_ics_creates_workspace_events_and_normalizes_all_day_end_dates(): void
    {
        $owner = User::factory()->create(['type' => 'human']);
        $content = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'SUMMARY:Launch day',
            'DTSTART;VALUE=DATE:20260519',
            'DTEND;VALUE=DATE:20260521',
            'DESCRIPTION:Ship\\, then review',
            'LOCATION:Remote',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        $events = app(ManageCalendarEvents::class)->importIcsContent($this->workspace, $owner, $content);

        $this->assertCount(1, $events);
        $this->assertSame($this->workspace->id, $events->first()->workspace_id);
        $this->assertSame('Launch day', $events->first()->title);
        $this->assertSame('Ship, then review', $events->first()->description);
        $this->assertSame('2026-05-20', $events->first()->end_at->toDateString());
        $this->assertTrue($events->first()->all_day);
    }
}
