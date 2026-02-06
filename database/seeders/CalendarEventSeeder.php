<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttendee;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class CalendarEventSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $events = $this->createEvents();
        $this->createAttendees($events);
    }

    private function createEvents(): array
    {
        $events = [
            // All-day events
            [
                'title' => 'Team Offsite',
                'description' => 'Full team offsite at the co-working space downtown. Focus on Q1 planning and team building.',
                'start_at' => '2026-02-12 00:00:00',
                'end_at' => '2026-02-13 23:59:59',
                'all_day' => true,
                'location' => 'WeWork Downtown',
                'color' => '#3b82f6',
                'created_by' => 'h1',
            ],
            [
                'title' => 'Company Holiday',
                'description' => 'Office closed for national holiday.',
                'start_at' => '2026-03-06 00:00:00',
                'end_at' => '2026-03-06 23:59:59',
                'all_day' => true,
                'location' => null,
                'color' => '#ef4444',
                'created_by' => 'a1',
            ],

            // Multi-hour events
            [
                'title' => 'Sprint Planning',
                'description' => 'Plan the upcoming two-week sprint. Review backlog, estimate stories, and assign tasks.',
                'start_at' => '2026-02-09 10:00:00',
                'end_at' => '2026-02-09 12:00:00',
                'all_day' => false,
                'location' => 'Main Conference Room',
                'color' => '#8b5cf6',
                'created_by' => 'a1',
            ],
            [
                'title' => 'Design Review',
                'description' => 'Review the latest mockups for the dashboard redesign. Bring feedback and suggestions.',
                'start_at' => '2026-02-17 14:00:00',
                'end_at' => '2026-02-17 16:00:00',
                'all_day' => false,
                'location' => null,
                'color' => '#f59e0b',
                'created_by' => 'a4',
            ],
            [
                'title' => '1:1 with Atlas',
                'description' => 'Weekly check-in to review priorities and blockers.',
                'start_at' => '2026-02-20 11:00:00',
                'end_at' => '2026-02-20 12:00:00',
                'all_day' => false,
                'location' => null,
                'color' => null,
                'created_by' => 'h1',
            ],
            [
                'title' => 'Architecture Deep Dive',
                'description' => 'Technical session on the new microservices architecture proposal. Logic will present the RFC.',
                'start_at' => '2026-02-25 13:00:00',
                'end_at' => '2026-02-25 15:30:00',
                'all_day' => false,
                'location' => 'Virtual - Zoom',
                'color' => '#06b6d4',
                'created_by' => 'a5',
            ],
            [
                'title' => 'Sprint Retrospective',
                'description' => 'Reflect on the completed sprint. What went well, what could improve, action items.',
                'start_at' => '2026-03-02 15:00:00',
                'end_at' => '2026-03-02 16:30:00',
                'all_day' => false,
                'location' => 'Main Conference Room',
                'color' => '#8b5cf6',
                'created_by' => 'a1',
            ],
            [
                'title' => 'User Research Debrief',
                'description' => 'Scout presents findings from the latest round of user interviews. Discussion on next steps.',
                'start_at' => '2026-03-11 10:00:00',
                'end_at' => '2026-03-11 11:30:00',
                'all_day' => false,
                'location' => null,
                'color' => '#10b981',
                'created_by' => 'a6',
            ],

            // Short meetings
            [
                'title' => 'Daily Standup',
                'description' => 'Quick sync on progress, plans, and blockers.',
                'start_at' => '2026-02-10 09:00:00',
                'end_at' => '2026-02-10 09:15:00',
                'all_day' => false,
                'location' => null,
                'color' => null,
                'created_by' => 'a1',
            ],
            [
                'title' => 'Quick Sync',
                'description' => 'Brief alignment on the analytics integration timeline.',
                'start_at' => '2026-02-19 15:30:00',
                'end_at' => '2026-02-19 15:45:00',
                'all_day' => false,
                'location' => null,
                'color' => null,
                'created_by' => 'h1',
            ],
            [
                'title' => 'Content Review',
                'description' => 'Review blog post drafts and social media calendar for March.',
                'start_at' => '2026-03-05 11:00:00',
                'end_at' => '2026-03-05 11:30:00',
                'all_day' => false,
                'location' => null,
                'color' => '#f59e0b',
                'created_by' => 'a2',
            ],
            [
                'title' => 'Metrics Review',
                'description' => 'Monthly review of key performance metrics and analytics dashboard.',
                'start_at' => '2026-03-18 14:00:00',
                'end_at' => '2026-03-18 15:00:00',
                'all_day' => false,
                'location' => 'Virtual - Zoom',
                'color' => '#10b981',
                'created_by' => 'a3',
            ],
        ];

        $createdEvents = [];

        foreach ($events as $eventData) {
            $event = CalendarEvent::create([
                'id' => Str::uuid()->toString(),
                'title' => $eventData['title'],
                'description' => $eventData['description'],
                'start_at' => $eventData['start_at'],
                'end_at' => $eventData['end_at'],
                'all_day' => $eventData['all_day'],
                'location' => $eventData['location'],
                'color' => $eventData['color'],
                'created_by' => $eventData['created_by'],
            ]);

            $createdEvents[] = $event;
        }

        return $createdEvents;
    }

    private function createAttendees(array $events): void
    {
        $allUsers = ['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'];
        $statuses = ['pending', 'accepted', 'declined', 'tentative'];

        // Map of event index to specific attendee sets for realism
        $attendeeMap = [
            // Team Offsite - broad attendance
            0 => [
                'h1' => 'accepted',
                'a1' => 'accepted',
                'a4' => 'accepted',
                'a5' => 'tentative',
            ],
            // Company Holiday - broad attendance
            1 => [
                'h1' => 'accepted',
                'a1' => 'accepted',
                'a2' => 'accepted',
            ],
            // Sprint Planning
            2 => [
                'h1' => 'accepted',
                'a5' => 'accepted',
                'a3' => 'pending',
            ],
            // Design Review
            3 => [
                'h1' => 'accepted',
                'a4' => 'accepted',
                'a5' => 'tentative',
                'a2' => 'pending',
            ],
            // 1:1 with Atlas
            4 => [
                'h1' => 'accepted',
                'a1' => 'accepted',
            ],
            // Architecture Deep Dive
            5 => [
                'a5' => 'accepted',
                'h1' => 'accepted',
                'a1' => 'accepted',
                'a3' => 'tentative',
            ],
            // Sprint Retrospective
            6 => [
                'h1' => 'accepted',
                'a1' => 'accepted',
                'a5' => 'accepted',
                'a4' => 'declined',
            ],
            // User Research Debrief
            7 => [
                'a6' => 'accepted',
                'h1' => 'accepted',
                'a1' => 'pending',
                'a4' => 'accepted',
            ],
            // Daily Standup
            8 => [
                'h1' => 'accepted',
                'a1' => 'accepted',
                'a5' => 'accepted',
            ],
            // Quick Sync
            9 => [
                'h1' => 'accepted',
                'a3' => 'accepted',
                'a5' => 'pending',
            ],
            // Content Review
            10 => [
                'a2' => 'accepted',
                'h1' => 'tentative',
                'a4' => 'accepted',
            ],
            // Metrics Review
            11 => [
                'a3' => 'accepted',
                'h1' => 'accepted',
                'a1' => 'accepted',
                'a6' => 'pending',
            ],
        ];

        foreach ($events as $index => $event) {
            $attendees = $attendeeMap[$index] ?? [];

            foreach ($attendees as $userId => $status) {
                CalendarEventAttendee::create([
                    'id' => Str::uuid()->toString(),
                    'event_id' => $event->id,
                    'user_id' => $userId,
                    'status' => $status,
                ]);
            }
        }
    }
}
