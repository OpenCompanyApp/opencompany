<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $notifications = [
            // For human user (h1)
            [
                'type' => 'approval',
                'title' => 'New Approval Request',
                'message' => 'Logic requested approval for Cloud Infrastructure Upgrade ($250.00)',
                'user_id' => 'h1',
                'is_read' => false,
                'action_url' => '/approvals',
                'actor_id' => 'a5',
                'metadata' => ['approval_id' => 'approval-1', 'amount' => 250.00],
                'hours_ago' => 2,
            ],
            [
                'type' => 'approval',
                'title' => 'Deployment Approval Needed',
                'message' => 'Logic wants to deploy Authentication Module to production',
                'user_id' => 'h1',
                'is_read' => false,
                'action_url' => '/approvals',
                'actor_id' => 'a5',
                'metadata' => ['approval_id' => 'approval-2', 'type' => 'deployment'],
                'hours_ago' => 5,
            ],
            [
                'type' => 'task',
                'title' => 'Task Completed',
                'message' => 'Nova completed "Quarterly metrics analysis"',
                'user_id' => 'h1',
                'is_read' => false,
                'action_url' => '/tasks',
                'actor_id' => 'a3',
                'metadata' => ['task_id' => 'task-metrics'],
                'hours_ago' => 8,
            ],
            [
                'type' => 'mention',
                'title' => 'You were mentioned',
                'message' => 'Atlas mentioned you in #general: "@Rutger, ready for the sync call?"',
                'user_id' => 'h1',
                'is_read' => true,
                'action_url' => '/chat?channel=ch1',
                'actor_id' => 'a1',
                'metadata' => ['channel_id' => 'ch1', 'message_id' => 'msg-1'],
                'hours_ago' => 24,
            ],
            [
                'type' => 'task',
                'title' => 'Task Completed',
                'message' => 'Pixel completed "Design dashboard mockups"',
                'user_id' => 'h1',
                'is_read' => true,
                'action_url' => '/tasks',
                'actor_id' => 'a4',
                'metadata' => ['task_id' => 'task-mockups'],
                'hours_ago' => 36,
            ],
            [
                'type' => 'system',
                'title' => 'Weekly Report Ready',
                'message' => 'Your weekly activity report is ready to view',
                'user_id' => 'h1',
                'is_read' => true,
                'action_url' => '/activity',
                'actor_id' => null,
                'metadata' => ['report_type' => 'weekly'],
                'hours_ago' => 48,
            ],

            // For Atlas (a1) - Manager
            [
                'type' => 'task',
                'title' => 'Task Completed',
                'message' => 'Logic completed "Set up authentication system"',
                'user_id' => 'a1',
                'is_read' => true,
                'action_url' => '/tasks',
                'actor_id' => 'a5',
                'metadata' => ['task_id' => 'task-auth'],
                'hours_ago' => 12,
            ],
            [
                'type' => 'mention',
                'title' => 'You were mentioned',
                'message' => 'Rutger mentioned you in #agent-ops',
                'user_id' => 'a1',
                'is_read' => false,
                'action_url' => '/chat?channel=ch4',
                'actor_id' => 'h1',
                'metadata' => ['channel_id' => 'ch4'],
                'hours_ago' => 4,
            ],

            // For Logic (a5) - Coder
            [
                'type' => 'approval',
                'title' => 'Approval Granted',
                'message' => 'Rutger approved your deployment request for Dashboard Updates',
                'user_id' => 'a5',
                'is_read' => true,
                'action_url' => '/approvals',
                'actor_id' => 'h1',
                'metadata' => ['approval_id' => 'approval-deploy'],
                'hours_ago' => 72,
            ],
            [
                'type' => 'task',
                'title' => 'New Task Assigned',
                'message' => 'Atlas assigned you to "Database optimization"',
                'user_id' => 'a5',
                'is_read' => false,
                'action_url' => '/tasks',
                'actor_id' => 'a1',
                'metadata' => ['task_id' => 'task-db'],
                'hours_ago' => 6,
            ],

            // For Nova (a3) - Analyst
            [
                'type' => 'task',
                'title' => 'New Task Assigned',
                'message' => 'Atlas assigned you to "Security audit"',
                'user_id' => 'a3',
                'is_read' => false,
                'action_url' => '/tasks',
                'actor_id' => 'a1',
                'metadata' => ['task_id' => 'task-security'],
                'hours_ago' => 3,
            ],
            [
                'type' => 'message',
                'title' => 'New Comment',
                'message' => 'Rutger commented on your task "Performance optimization"',
                'user_id' => 'a3',
                'is_read' => true,
                'action_url' => '/tasks',
                'actor_id' => 'h1',
                'metadata' => ['task_id' => 'task-perf'],
                'hours_ago' => 18,
            ],

            // For Pixel (a4) - Creative
            [
                'type' => 'approval',
                'title' => 'Approval Granted',
                'message' => 'Rutger approved your budget request for Design Tool Subscription',
                'user_id' => 'a4',
                'is_read' => true,
                'action_url' => '/approvals',
                'actor_id' => 'h1',
                'metadata' => ['approval_id' => 'approval-design'],
                'hours_ago' => 120,
            ],
            [
                'type' => 'mention',
                'title' => 'You were mentioned',
                'message' => 'Echo mentioned you in #design: "Great mockups @Pixel!"',
                'user_id' => 'a4',
                'is_read' => false,
                'action_url' => '/chat?channel=ch3',
                'actor_id' => 'a2',
                'metadata' => ['channel_id' => 'ch3'],
                'hours_ago' => 10,
            ],

            // For Echo (a2) - Writer
            [
                'type' => 'task',
                'title' => 'New Task Assigned',
                'message' => 'Atlas assigned you to "Blog content calendar"',
                'user_id' => 'a2',
                'is_read' => false,
                'action_url' => '/tasks',
                'actor_id' => 'a1',
                'metadata' => ['task_id' => 'task-blog'],
                'hours_ago' => 8,
            ],
            [
                'type' => 'message',
                'title' => 'Document Shared',
                'message' => 'Scout shared "Competitor Analysis" with you',
                'user_id' => 'a2',
                'is_read' => true,
                'action_url' => '/docs',
                'actor_id' => 'a6',
                'metadata' => ['document_id' => 'doc-competitor'],
                'hours_ago' => 48,
            ],

            // For Scout (a6) - Researcher
            [
                'type' => 'approval',
                'title' => 'Approval Granted',
                'message' => 'Rutger approved your budget request for Research Data Purchase',
                'user_id' => 'a6',
                'is_read' => true,
                'action_url' => '/approvals',
                'actor_id' => 'h1',
                'metadata' => ['approval_id' => 'approval-research'],
                'hours_ago' => 144,
            ],
            [
                'type' => 'message',
                'title' => 'New Comment',
                'message' => 'Atlas commented on your document "User Research Findings"',
                'user_id' => 'a6',
                'is_read' => false,
                'action_url' => '/docs',
                'actor_id' => 'a1',
                'metadata' => ['document_id' => 'doc-research'],
                'hours_ago' => 16,
            ],
        ];

        foreach ($notifications as $notifData) {
            Notification::create([
                'id' => Str::uuid()->toString(),
                'type' => $notifData['type'],
                'title' => $notifData['title'],
                'message' => $notifData['message'],
                'user_id' => $notifData['user_id'],
                'is_read' => $notifData['is_read'],
                'action_url' => $notifData['action_url'],
                'actor_id' => $notifData['actor_id'],
                'metadata' => $notifData['metadata'],
                'created_at' => now()->subHours($notifData['hours_ago']),
            ]);
        }
    }
}
