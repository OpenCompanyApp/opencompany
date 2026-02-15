<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskStep;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class AgentTaskSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $workspaceId = $workspace->id;

        $tasks = [
            // Completed tasks
            [
                'title' => 'Resolve customer billing inquiry',
                'description' => 'Customer reported duplicate charge on their account. Investigate and resolve.',
                'type' => 'ticket',
                'status' => 'completed',
                'priority' => 'high',
                'agent_id' => 'a1',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDays(1),
                'result' => ['resolution' => 'Duplicate charge refunded', 'amount' => 49.99],
                'steps' => [
                    ['description' => 'Reviewed customer account history', 'status' => 'completed'],
                    ['description' => 'Identified duplicate transaction', 'status' => 'completed'],
                    ['description' => 'Processed refund request', 'status' => 'completed'],
                    ['description' => 'Notified customer of resolution', 'status' => 'completed'],
                ],
            ],
            [
                'title' => 'Generate weekly performance report',
                'description' => 'Compile and analyze weekly metrics for the leadership team.',
                'type' => 'analysis',
                'status' => 'completed',
                'priority' => 'normal',
                'agent_id' => 'a3',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
                'started_at' => now()->subDays(3),
                'completed_at' => now()->subDays(2),
                'result' => ['report_url' => '/reports/weekly-2026-02-03.pdf', 'summary' => 'Overall metrics up 12% from last week'],
                'steps' => [
                    ['description' => 'Gathered data from all sources', 'status' => 'completed'],
                    ['description' => 'Performed statistical analysis', 'status' => 'completed'],
                    ['description' => 'Created visualizations', 'status' => 'completed'],
                    ['description' => 'Generated final report', 'status' => 'completed'],
                ],
            ],
            [
                'title' => 'Write product update blog post',
                'description' => 'Create a blog post announcing the new features released this month.',
                'type' => 'content',
                'status' => 'completed',
                'priority' => 'medium',
                'agent_id' => 'a2',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
                'started_at' => now()->subDays(4),
                'completed_at' => now()->subDays(3),
                'result' => ['draft_url' => '/drafts/product-update-feb.md', 'word_count' => 1250],
                'steps' => [
                    ['description' => 'Researched new features', 'status' => 'completed'],
                    ['description' => 'Created outline', 'status' => 'completed'],
                    ['description' => 'Wrote first draft', 'status' => 'completed'],
                    ['description' => 'Edited and finalized', 'status' => 'completed'],
                ],
            ],

            // Active tasks
            [
                'title' => 'Investigate API latency issues',
                'description' => 'Users reporting slow response times. Investigate root cause and propose fixes.',
                'type' => 'analysis',
                'status' => 'active',
                'priority' => 'high',
                'agent_id' => 'a5',
                'requester_id' => 'h1',
                'channel_id' => 'ch2',
                'started_at' => now()->subHours(3),
                'steps' => [
                    ['description' => 'Reviewing server logs', 'status' => 'completed'],
                    ['description' => 'Analyzing database query performance', 'status' => 'in_progress'],
                    ['description' => 'Identify bottlenecks', 'status' => 'pending'],
                    ['description' => 'Propose optimization plan', 'status' => 'pending'],
                ],
            ],
            [
                'title' => 'Respond to partnership inquiry',
                'description' => 'Potential partner reached out for integration discussion. Prepare response.',
                'type' => 'request',
                'status' => 'active',
                'priority' => 'normal',
                'agent_id' => 'a1',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
                'started_at' => now()->subHours(1),
                'steps' => [
                    ['description' => 'Reviewed partner company profile', 'status' => 'completed'],
                    ['description' => 'Drafting integration possibilities', 'status' => 'in_progress'],
                    ['description' => 'Prepare response email', 'status' => 'pending'],
                ],
            ],
            [
                'title' => 'Research competitor pricing changes',
                'description' => 'Competitor announced new pricing. Research and analyze impact.',
                'type' => 'research',
                'status' => 'active',
                'priority' => 'medium',
                'agent_id' => 'a6',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
                'started_at' => now()->subHours(2),
                'steps' => [
                    ['description' => 'Collected competitor pricing data', 'status' => 'completed'],
                    ['description' => 'Analyzing pricing strategies', 'status' => 'in_progress'],
                    ['description' => 'Compare with our offerings', 'status' => 'pending'],
                    ['description' => 'Prepare recommendations', 'status' => 'pending'],
                ],
            ],

            // Paused task
            [
                'title' => 'Design new onboarding email sequence',
                'description' => 'Create a 5-email welcome sequence for new users.',
                'type' => 'content',
                'status' => 'paused',
                'priority' => 'low',
                'agent_id' => 'a2',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
                'started_at' => now()->subDays(1),
                'steps' => [
                    ['description' => 'Outlined email sequence', 'status' => 'completed'],
                    ['description' => 'Wrote welcome email', 'status' => 'completed'],
                    ['description' => 'Write feature highlight email', 'status' => 'pending'],
                    ['description' => 'Write best practices email', 'status' => 'pending'],
                    ['description' => 'Write engagement email', 'status' => 'pending'],
                ],
            ],

            // Pending tasks
            [
                'title' => 'Process customer feedback batch',
                'description' => 'Review and categorize the latest batch of customer feedback submissions.',
                'type' => 'ticket',
                'status' => 'pending',
                'priority' => 'normal',
                'agent_id' => 'a3',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
            ],
            [
                'title' => 'Create social media content calendar',
                'description' => 'Plan content for the next two weeks across all social channels.',
                'type' => 'content',
                'status' => 'pending',
                'priority' => 'medium',
                'agent_id' => 'a4',
                'requester_id' => 'h1',
                'channel_id' => 'ch3',
            ],
            [
                'title' => 'Analyze user engagement metrics',
                'description' => 'Deep dive into user engagement patterns and identify improvement areas.',
                'type' => 'analysis',
                'status' => 'pending',
                'priority' => 'normal',
                'agent_id' => 'a3',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
            ],
            [
                'title' => 'Investigate feature request feasibility',
                'description' => 'Customer requested custom export format. Research implementation effort.',
                'type' => 'research',
                'status' => 'pending',
                'priority' => 'low',
                'agent_id' => 'a6',
                'requester_id' => 'h1',
            ],
            [
                'title' => 'Handle urgent support escalation',
                'description' => 'Enterprise customer experiencing critical issue. Investigate immediately.',
                'type' => 'ticket',
                'status' => 'pending',
                'priority' => 'urgent',
                'agent_id' => 'a1',
                'requester_id' => 'h1',
                'channel_id' => 'ch1',
            ],

            // Failed task
            [
                'title' => 'Migrate legacy data format',
                'description' => 'Attempted to migrate old CSV data but encountered format issues.',
                'type' => 'custom',
                'status' => 'failed',
                'priority' => 'normal',
                'agent_id' => 'a5',
                'requester_id' => 'h1',
                'channel_id' => 'ch2',
                'started_at' => now()->subDays(1),
                'completed_at' => now()->subHours(18),
                'result' => ['error' => 'Data format incompatible. Manual review required for 234 records.'],
                'steps' => [
                    ['description' => 'Analyzed source data format', 'status' => 'completed'],
                    ['description' => 'Created migration script', 'status' => 'completed'],
                    ['description' => 'Executed migration', 'status' => 'completed'],
                    ['description' => 'Validation failed - incompatible records found', 'status' => 'completed'],
                ],
            ],
        ];

        foreach ($tasks as $taskData) {
            $steps = $taskData['steps'] ?? [];
            unset($taskData['steps']);

            $task = Task::create([
                'id' => Str::uuid()->toString(),
                'title' => $taskData['title'],
                'description' => $taskData['description'],
                'type' => $taskData['type'],
                'status' => $taskData['status'],
                'priority' => $taskData['priority'],
                'agent_id' => $taskData['agent_id'],
                'requester_id' => $taskData['requester_id'],
                'channel_id' => $taskData['channel_id'] ?? null,
                'started_at' => $taskData['started_at'] ?? null,
                'completed_at' => $taskData['completed_at'] ?? null,
                'result' => $taskData['result'] ?? null,
                'workspace_id' => $workspaceId,
            ]);

            // Create steps
            foreach ($steps as $index => $stepData) {
                TaskStep::create([
                    'id' => Str::uuid()->toString(),
                    'task_id' => $task->id,
                    'description' => $stepData['description'],
                    'status' => $stepData['status'],
                    'step_type' => 'action',
                    'started_at' => $stepData['status'] !== 'pending' ? now()->subHours(rand(1, 12)) : null,
                    'completed_at' => $stepData['status'] === 'completed' ? now()->subMinutes(rand(10, 120)) : null,
                ]);
            }
        }
    }
}
