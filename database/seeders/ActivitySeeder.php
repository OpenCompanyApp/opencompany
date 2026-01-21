<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityStep;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class ActivitySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->createActivities();
        $this->createActivitySteps();
    }

    private function createActivities(): void
    {
        $activities = [
            // Recent activities
            [
                'type' => 'task_completed',
                'description' => 'Completed task: Set up authentication system',
                'actor_id' => 'a5',
                'metadata' => ['task_id' => 'task-auth', 'cost' => 45.50],
                'days_ago' => 0,
            ],
            [
                'type' => 'message',
                'description' => 'Sent message in #engineering channel',
                'actor_id' => 'a5',
                'metadata' => ['channel_id' => 'ch2', 'preview' => 'Deployed the auth module...'],
                'days_ago' => 0,
            ],
            [
                'type' => 'approval_granted',
                'description' => 'Approval granted: Deploy Dashboard Updates',
                'actor_id' => 'h1',
                'metadata' => ['approval_id' => 'approval-1', 'type' => 'deployment'],
                'days_ago' => 0,
            ],
            [
                'type' => 'task_started',
                'description' => 'Started working on: Implement notification system',
                'actor_id' => 'a5',
                'metadata' => ['task_id' => 'task-notifications'],
                'days_ago' => 0,
            ],
            [
                'type' => 'message',
                'description' => 'Posted in #general channel',
                'actor_id' => 'h1',
                'metadata' => ['channel_id' => 'ch1', 'preview' => 'Great work team!'],
                'days_ago' => 0,
            ],

            // Yesterday
            [
                'type' => 'task_completed',
                'description' => 'Completed task: Design dashboard mockups',
                'actor_id' => 'a4',
                'metadata' => ['task_id' => 'task-mockups', 'cost' => 32.00],
                'days_ago' => 1,
            ],
            [
                'type' => 'agent_spawned',
                'description' => 'Agent Logic started working',
                'actor_id' => 'a1',
                'metadata' => ['agent_id' => 'a5', 'task' => 'API development'],
                'days_ago' => 1,
            ],
            [
                'type' => 'approval_needed',
                'description' => 'New approval request: Cloud Infrastructure Upgrade',
                'actor_id' => 'a5',
                'metadata' => ['approval_id' => 'approval-2', 'amount' => 250.00],
                'days_ago' => 1,
            ],
            [
                'type' => 'message',
                'description' => 'Shared research findings in #general',
                'actor_id' => 'a6',
                'metadata' => ['channel_id' => 'ch1'],
                'days_ago' => 1,
            ],

            // This week
            [
                'type' => 'task_completed',
                'description' => 'Completed task: Write API documentation',
                'actor_id' => 'a2',
                'metadata' => ['task_id' => 'task-docs', 'cost' => 18.25],
                'days_ago' => 2,
            ],
            [
                'type' => 'task_completed',
                'description' => 'Completed task: Quarterly metrics analysis',
                'actor_id' => 'a3',
                'metadata' => ['task_id' => 'task-metrics', 'cost' => 28.75],
                'days_ago' => 2,
            ],
            [
                'type' => 'agent_spawned',
                'description' => 'Agent Nova started analysis task',
                'actor_id' => 'a1',
                'metadata' => ['agent_id' => 'a3', 'task' => 'Q4 metrics analysis'],
                'days_ago' => 3,
            ],
            [
                'type' => 'approval_granted',
                'description' => 'Approval granted: Design Tool Subscription',
                'actor_id' => 'h1',
                'metadata' => ['approval_id' => 'approval-3', 'amount' => 180.00],
                'days_ago' => 3,
            ],
            [
                'type' => 'message',
                'description' => 'Design review discussion in #design',
                'actor_id' => 'a4',
                'metadata' => ['channel_id' => 'ch3'],
                'days_ago' => 3,
            ],
            [
                'type' => 'task_started',
                'description' => 'Started working on: Performance optimization',
                'actor_id' => 'a3',
                'metadata' => ['task_id' => 'task-perf'],
                'days_ago' => 4,
            ],
            [
                'type' => 'error',
                'description' => 'Build failed: Missing dependency',
                'actor_id' => 'a5',
                'metadata' => ['error_code' => 'DEP001', 'resolved' => true],
                'days_ago' => 4,
            ],
            [
                'type' => 'task_started',
                'description' => 'Started working on: Competitor research',
                'actor_id' => 'a6',
                'metadata' => ['task_id' => 'task-research'],
                'days_ago' => 5,
            ],
            [
                'type' => 'message',
                'description' => 'Sprint planning discussion in #agent-ops',
                'actor_id' => 'a1',
                'metadata' => ['channel_id' => 'ch4'],
                'days_ago' => 5,
            ],
            [
                'type' => 'agent_spawned',
                'description' => 'Agent Echo started documentation task',
                'actor_id' => 'a1',
                'metadata' => ['agent_id' => 'a2', 'task' => 'API docs'],
                'days_ago' => 6,
            ],
            [
                'type' => 'approval_granted',
                'description' => 'Approval granted: Research Data Purchase',
                'actor_id' => 'h1',
                'metadata' => ['approval_id' => 'approval-4', 'amount' => 75.00],
                'days_ago' => 6,
            ],

            // Last week
            [
                'type' => 'task_completed',
                'description' => 'Completed task: User interview sessions',
                'actor_id' => 'a6',
                'metadata' => ['task_id' => 'task-interviews', 'cost' => 22.00],
                'days_ago' => 8,
            ],
            [
                'type' => 'message',
                'description' => 'Kick-off meeting in #general',
                'actor_id' => 'h1',
                'metadata' => ['channel_id' => 'ch1'],
                'days_ago' => 9,
            ],
            [
                'type' => 'agent_spawned',
                'description' => 'Agent Scout started research',
                'actor_id' => 'a1',
                'metadata' => ['agent_id' => 'a6', 'task' => 'User research'],
                'days_ago' => 10,
            ],
            [
                'type' => 'task_started',
                'description' => 'Started working on: Brand guidelines update',
                'actor_id' => 'a4',
                'metadata' => ['task_id' => 'task-brand'],
                'days_ago' => 11,
            ],
            [
                'type' => 'error',
                'description' => 'API rate limit exceeded',
                'actor_id' => 'a3',
                'metadata' => ['error_code' => 'RATE001', 'resolved' => true],
                'days_ago' => 12,
            ],
        ];

        foreach ($activities as $activityData) {
            Activity::create([
                'id' => Str::uuid()->toString(),
                'type' => $activityData['type'],
                'description' => $activityData['description'],
                'actor_id' => $activityData['actor_id'],
                'metadata' => $activityData['metadata'],
                'timestamp' => now()->subDays($activityData['days_ago'])->subHours(rand(0, 12)),
                'created_at' => now()->subDays($activityData['days_ago'])->subHours(rand(0, 12)),
            ]);
        }
    }

    private function createActivitySteps(): void
    {
        // Activity steps for working agents
        $agentSteps = [
            'a1' => [ // Atlas - Manager
                ['Reviewing task queue', 'completed'],
                ['Prioritizing items for sprint', 'completed'],
                ['Coordinating team assignments', 'in_progress'],
            ],
            'a3' => [ // Nova - Analyst
                ['Gathering performance data', 'completed'],
                ['Running statistical analysis', 'completed'],
                ['Generating visualizations', 'in_progress'],
                ['Preparing executive summary', 'pending'],
            ],
            'a5' => [ // Logic - Coder
                ['Setting up notification service', 'completed'],
                ['Implementing WebSocket handlers', 'completed'],
                ['Building notification UI components', 'in_progress'],
                ['Writing unit tests', 'pending'],
                ['Preparing deployment', 'pending'],
            ],
        ];

        foreach ($agentSteps as $userId => $steps) {
            foreach ($steps as $index => $step) {
                [$description, $status] = $step;

                $startedAt = now()->subHours(count($steps) - $index);
                $completedAt = $status === 'completed' ? $startedAt->copy()->addMinutes(rand(15, 45)) : null;

                ActivityStep::create([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userId,
                    'description' => $description,
                    'status' => $status,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                ]);
            }
        }
    }
}
