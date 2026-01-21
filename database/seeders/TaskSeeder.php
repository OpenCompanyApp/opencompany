<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskCollaborator;
use App\Models\TaskComment;
use App\Models\TaskTemplate;
use App\Models\TaskAutomationRule;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class TaskSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->createTasks();
        $this->createTaskTemplates();
        $this->createAutomationRules();
    }

    private function createTasks(): void
    {
        $tasks = [
            // Completed tasks
            [
                'title' => 'Set up authentication system',
                'description' => 'Implement JWT-based authentication with refresh tokens and secure session management.',
                'status' => 'done',
                'assignee_id' => 'a5',
                'priority' => 'high',
                'cost' => 45.50,
                'estimated_cost' => 50.00,
                'channel_id' => 'ch2',
                'completed_at' => now()->subDays(3),
            ],
            [
                'title' => 'Design dashboard mockups',
                'description' => 'Create high-fidelity mockups for the new analytics dashboard including mobile layouts.',
                'status' => 'done',
                'assignee_id' => 'a4',
                'priority' => 'high',
                'cost' => 32.00,
                'estimated_cost' => 35.00,
                'channel_id' => 'ch3',
                'completed_at' => now()->subDays(2),
            ],
            [
                'title' => 'Write API documentation',
                'description' => 'Document all REST API endpoints with examples and authentication requirements.',
                'status' => 'done',
                'assignee_id' => 'a2',
                'priority' => 'medium',
                'cost' => 18.25,
                'estimated_cost' => 20.00,
                'channel_id' => 'ch2',
                'completed_at' => now()->subDays(1),
            ],
            [
                'title' => 'Quarterly metrics analysis',
                'description' => 'Analyze Q4 performance metrics and prepare executive summary.',
                'status' => 'done',
                'assignee_id' => 'a3',
                'priority' => 'high',
                'cost' => 28.75,
                'estimated_cost' => 30.00,
                'channel_id' => 'ch1',
                'completed_at' => now()->subHours(12),
            ],

            // In progress tasks
            [
                'title' => 'Implement notification system',
                'description' => 'Build real-time notifications using WebSockets with read/unread tracking.',
                'status' => 'in_progress',
                'assignee_id' => 'a5',
                'priority' => 'high',
                'cost' => 22.00,
                'estimated_cost' => 40.00,
                'channel_id' => 'ch2',
            ],
            [
                'title' => 'User onboarding flow',
                'description' => 'Design and implement the new user onboarding experience with guided tour.',
                'status' => 'in_progress',
                'assignee_id' => 'a4',
                'priority' => 'medium',
                'cost' => 15.50,
                'estimated_cost' => 25.00,
                'channel_id' => 'ch3',
            ],
            [
                'title' => 'Performance optimization',
                'description' => 'Identify and fix performance bottlenecks in the main application.',
                'status' => 'in_progress',
                'assignee_id' => 'a3',
                'priority' => 'high',
                'cost' => 12.00,
                'estimated_cost' => 35.00,
                'channel_id' => 'ch2',
            ],
            [
                'title' => 'Competitor research report',
                'description' => 'Research and document competitor features, pricing, and market positioning.',
                'status' => 'in_progress',
                'assignee_id' => 'a6',
                'priority' => 'medium',
                'cost' => 8.50,
                'estimated_cost' => 20.00,
                'channel_id' => 'ch1',
            ],

            // Backlog tasks (ready to be picked up)
            [
                'title' => 'Implement dark mode',
                'description' => 'Add dark mode theme support across all components.',
                'status' => 'backlog',
                'assignee_id' => 'a4',
                'priority' => 'low',
                'estimated_cost' => 15.00,
                'channel_id' => 'ch3',
            ],
            [
                'title' => 'Database optimization',
                'description' => 'Optimize database queries and add proper indexing.',
                'status' => 'backlog',
                'assignee_id' => 'a5',
                'priority' => 'medium',
                'estimated_cost' => 25.00,
                'channel_id' => 'ch2',
            ],
            [
                'title' => 'Mobile app research',
                'description' => 'Research React Native vs Flutter for mobile app development.',
                'status' => 'backlog',
                'assignee_id' => 'a6',
                'priority' => 'low',
                'estimated_cost' => 12.00,
                'channel_id' => 'ch1',
            ],
            [
                'title' => 'Security audit',
                'description' => 'Conduct comprehensive security audit of the application.',
                'status' => 'backlog',
                'assignee_id' => 'a3',
                'priority' => 'high',
                'estimated_cost' => 45.00,
                'channel_id' => 'ch2',
            ],
            [
                'title' => 'Blog content calendar',
                'description' => 'Plan content calendar for Q1 blog posts and thought leadership.',
                'status' => 'backlog',
                'assignee_id' => 'a2',
                'priority' => 'medium',
                'estimated_cost' => 18.00,
                'channel_id' => 'ch1',
            ],
            [
                'title' => 'Integration with Slack',
                'description' => 'Build Slack integration for notifications and commands.',
                'status' => 'backlog',
                'assignee_id' => 'a5',
                'priority' => 'medium',
                'estimated_cost' => 30.00,
                'channel_id' => 'ch2',
            ],
            [
                'title' => 'User feedback analysis',
                'description' => 'Analyze recent user feedback and prioritize improvements.',
                'status' => 'backlog',
                'assignee_id' => 'a3',
                'priority' => 'medium',
                'estimated_cost' => 15.00,
                'channel_id' => 'ch1',
            ],
            [
                'title' => 'AI model fine-tuning',
                'description' => 'Fine-tune language models for improved task understanding.',
                'status' => 'backlog',
                'assignee_id' => 'a1',
                'priority' => 'low',
                'estimated_cost' => 80.00,
                'channel_id' => 'ch2',
            ],
            [
                'title' => 'Multi-language support',
                'description' => 'Implement i18n for supporting multiple languages.',
                'status' => 'backlog',
                'assignee_id' => 'a2',
                'priority' => 'low',
                'estimated_cost' => 50.00,
                'channel_id' => 'ch1',
            ],
            [
                'title' => 'Advanced analytics dashboard',
                'description' => 'Build advanced analytics with custom reports and exports.',
                'status' => 'backlog',
                'assignee_id' => 'a3',
                'priority' => 'medium',
                'estimated_cost' => 65.00,
                'channel_id' => 'ch2',
            ],
            [
                'title' => 'Video conferencing integration',
                'description' => 'Integrate video calls directly into channels.',
                'status' => 'backlog',
                'assignee_id' => 'a5',
                'priority' => 'low',
                'estimated_cost' => 100.00,
                'channel_id' => 'ch1',
            ],
        ];

        $position = 0;
        $createdTasks = [];

        foreach ($tasks as $taskData) {
            $task = Task::create([
                'id' => Str::uuid()->toString(),
                'title' => $taskData['title'],
                'description' => $taskData['description'],
                'status' => $taskData['status'],
                'assignee_id' => $taskData['assignee_id'],
                'priority' => $taskData['priority'],
                'cost' => $taskData['cost'] ?? 0,
                'estimated_cost' => $taskData['estimated_cost'] ?? 0,
                'channel_id' => $taskData['channel_id'],
                'position' => $position++,
                'completed_at' => $taskData['completed_at'] ?? null,
            ]);

            $createdTasks[] = $task;

            // Add collaborators to some tasks
            if (rand(1, 10) <= 6 && $taskData['assignee_id']) {
                $collaborators = collect(['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'])
                    ->reject(fn($id) => $id === $taskData['assignee_id'])
                    ->random(rand(1, 2));

                foreach ($collaborators as $collaboratorId) {
                    TaskCollaborator::create([
                        'id' => Str::uuid()->toString(),
                        'task_id' => $task->id,
                        'user_id' => $collaboratorId,
                    ]);
                }
            }
        }

        // Add comments to tasks
        $this->createTaskComments($createdTasks);
    }

    private function createTaskComments(array $tasks): void
    {
        $comments = [
            'Great progress on this! Let me know if you need any help.',
            'I\'ve reviewed the implementation and it looks solid.',
            'Can we add some unit tests for edge cases?',
            'This is blocking another task. Let\'s prioritize it.',
            'Nice work! The code quality is excellent.',
            'I have a few suggestions for improvement.',
            'Let\'s discuss this in our next standup.',
            'Updated the documentation to reflect these changes.',
            'The performance improvements are noticeable.',
            'Should we consider a different approach here?',
        ];

        foreach (array_slice($tasks, 0, 12) as $task) {
            $commentCount = rand(1, 4);
            $parentCommentId = null;

            for ($i = 0; $i < $commentCount; $i++) {
                $comment = TaskComment::create([
                    'id' => Str::uuid()->toString(),
                    'task_id' => $task->id,
                    'author_id' => collect(['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'])->random(),
                    'content' => $comments[array_rand($comments)],
                    'parent_id' => ($i > 0 && rand(1, 3) === 1) ? $parentCommentId : null,
                    'created_at' => now()->subDays(rand(0, 7))->subHours(rand(0, 12)),
                ]);

                if ($i === 0) {
                    $parentCommentId = $comment->id;
                }
            }
        }
    }

    private function createTaskTemplates(): void
    {
        $templates = [
            [
                'name' => 'Bug Fix',
                'description' => 'Template for tracking bug fixes',
                'default_title' => 'Fix: [Bug Description]',
                'default_description' => "## Bug Description\n\n## Steps to Reproduce\n\n## Expected Behavior\n\n## Actual Behavior\n\n## Fix Details\n",
                'default_priority' => 'high',
                'default_assignee_id' => 'a5',
                'estimated_cost' => 15.00,
                'tags' => ['bug', 'fix', 'engineering'],
            ],
            [
                'name' => 'Feature Request',
                'description' => 'Template for new feature development',
                'default_title' => 'Feature: [Feature Name]',
                'default_description' => "## Feature Overview\n\n## User Story\n\n## Acceptance Criteria\n\n## Technical Notes\n",
                'default_priority' => 'medium',
                'default_assignee_id' => null,
                'estimated_cost' => 40.00,
                'tags' => ['feature', 'enhancement'],
            ],
            [
                'name' => 'Documentation',
                'description' => 'Template for documentation tasks',
                'default_title' => 'Docs: [Topic]',
                'default_description' => "## Documentation Scope\n\n## Audience\n\n## Key Points to Cover\n",
                'default_priority' => 'low',
                'default_assignee_id' => 'a2',
                'estimated_cost' => 10.00,
                'tags' => ['docs', 'writing'],
            ],
            [
                'name' => 'Research',
                'description' => 'Template for research tasks',
                'default_title' => 'Research: [Topic]',
                'default_description' => "## Research Question\n\n## Sources to Review\n\n## Deliverables\n",
                'default_priority' => 'medium',
                'default_assignee_id' => 'a6',
                'estimated_cost' => 20.00,
                'tags' => ['research', 'analysis'],
            ],
            [
                'name' => 'Design Review',
                'description' => 'Template for design review tasks',
                'default_title' => 'Design: [Component/Feature]',
                'default_description' => "## Design Goals\n\n## Requirements\n\n## Deliverables\n- [ ] Wireframes\n- [ ] High-fidelity mockups\n- [ ] Responsive variants\n",
                'default_priority' => 'medium',
                'default_assignee_id' => 'a4',
                'estimated_cost' => 25.00,
                'tags' => ['design', 'ui', 'ux'],
            ],
        ];

        foreach ($templates as $templateData) {
            TaskTemplate::create([
                'id' => Str::uuid()->toString(),
                'name' => $templateData['name'],
                'description' => $templateData['description'],
                'default_title' => $templateData['default_title'],
                'default_description' => $templateData['default_description'],
                'default_priority' => $templateData['default_priority'],
                'default_assignee_id' => $templateData['default_assignee_id'],
                'estimated_cost' => $templateData['estimated_cost'],
                'tags' => $templateData['tags'],
                'created_by_id' => 'h1',
                'is_active' => true,
            ]);
        }
    }

    private function createAutomationRules(): void
    {
        $bugTemplate = TaskTemplate::where('name', 'Bug Fix')->first();
        $featureTemplate = TaskTemplate::where('name', 'Feature Request')->first();

        $rules = [
            [
                'name' => 'Auto-assign bugs to Logic',
                'description' => 'Automatically assign bug tasks to the coder agent',
                'trigger_type' => 'task_created',
                'trigger_conditions' => ['tags_contain' => 'bug'],
                'action_type' => 'assign_task',
                'action_config' => ['user_id' => 'a5'],
                'template_id' => $bugTemplate?->id,
                'trigger_count' => 12,
                'last_triggered_at' => now()->subDays(2),
            ],
            [
                'name' => 'High priority notification',
                'description' => 'Notify manager when high priority tasks are created',
                'trigger_type' => 'task_created',
                'trigger_conditions' => ['priority' => 'high'],
                'action_type' => 'send_notification',
                'action_config' => ['user_id' => 'a1', 'message' => 'New high priority task created'],
                'template_id' => null,
                'trigger_count' => 8,
                'last_triggered_at' => now()->subDays(1),
            ],
            [
                'name' => 'Auto-update feature requests',
                'description' => 'Update feature requests with enhancement label',
                'trigger_type' => 'task_created',
                'trigger_conditions' => ['title_contains' => 'Feature:'],
                'action_type' => 'update_task',
                'action_config' => ['label' => 'enhancement'],
                'template_id' => $featureTemplate?->id,
                'trigger_count' => 5,
                'last_triggered_at' => now()->subDays(3),
            ],
            [
                'name' => 'Completion notification',
                'description' => 'Send notification when task is completed',
                'trigger_type' => 'task_completed',
                'trigger_conditions' => [],
                'action_type' => 'send_notification',
                'action_config' => ['channel_id' => 'ch1', 'message' => 'Task completed!'],
                'template_id' => null,
                'trigger_count' => 15,
                'last_triggered_at' => now()->subHours(6),
            ],
            [
                'name' => 'Spawn agent for new tasks',
                'description' => 'Automatically spawn an agent when approval is granted',
                'trigger_type' => 'approval_granted',
                'trigger_conditions' => ['task_type' => 'development'],
                'action_type' => 'spawn_agent',
                'action_config' => ['agent_type' => 'coder', 'message' => 'Starting work on approved task'],
                'template_id' => null,
                'trigger_count' => 3,
                'last_triggered_at' => now()->subDays(1),
            ],
        ];

        foreach ($rules as $ruleData) {
            TaskAutomationRule::create([
                'id' => Str::uuid()->toString(),
                'name' => $ruleData['name'],
                'description' => $ruleData['description'],
                'trigger_type' => $ruleData['trigger_type'],
                'trigger_conditions' => $ruleData['trigger_conditions'],
                'action_type' => $ruleData['action_type'],
                'action_config' => $ruleData['action_config'],
                'template_id' => $ruleData['template_id'],
                'is_active' => true,
                'trigger_count' => $ruleData['trigger_count'],
                'last_triggered_at' => $ruleData['last_triggered_at'],
                'created_by_id' => 'h1',
            ]);
        }
    }
}
