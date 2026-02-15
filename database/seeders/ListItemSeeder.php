<?php

namespace Database\Seeders;

use App\Models\ListItem;
use App\Models\ListItemCollaborator;
use App\Models\ListItemComment;
use App\Models\ListTemplate;
use App\Models\ListAutomationRule;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class ListItemSeeder extends Seeder
{
    use WithoutModelEvents;

    private string $workspaceId;

    public function run(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $this->workspaceId = $workspace->id;

        $this->createListItems();
        $this->createListTemplates();
        $this->createAutomationRules();
    }

    private function createListItems(): void
    {
        $items = [
            // Completed items
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

            // In progress items
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

            // Backlog items
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
        ];

        $position = 0;
        $createdItems = [];

        foreach ($items as $itemData) {
            $item = ListItem::create([
                'id' => Str::uuid()->toString(),
                'title' => $itemData['title'],
                'description' => $itemData['description'],
                'status' => $itemData['status'],
                'assignee_id' => $itemData['assignee_id'],
                'priority' => $itemData['priority'],
                'cost' => $itemData['cost'] ?? 0,
                'estimated_cost' => $itemData['estimated_cost'] ?? 0,
                'channel_id' => $itemData['channel_id'],
                'position' => $position++,
                'completed_at' => $itemData['completed_at'] ?? null,
                'workspace_id' => $this->workspaceId,
            ]);

            $createdItems[] = $item;

            // Add collaborators to some items
            if (rand(1, 10) <= 6 && $itemData['assignee_id']) {
                $collaborators = collect(['h1', 'a1', 'a2', 'a3', 'a4', 'a5', 'a6'])
                    ->reject(fn($id) => $id === $itemData['assignee_id'])
                    ->random(rand(1, 2));

                foreach ($collaborators as $collaboratorId) {
                    ListItemCollaborator::create([
                        'id' => Str::uuid()->toString(),
                        'list_item_id' => $item->id,
                        'user_id' => $collaboratorId,
                    ]);
                }
            }
        }

        // Add comments to items
        $this->createListItemComments($createdItems);
    }

    private function createListItemComments(array $items): void
    {
        $comments = [
            'Great progress on this! Let me know if you need any help.',
            'I\'ve reviewed the implementation and it looks solid.',
            'Can we add some unit tests for edge cases?',
            'This is blocking another item. Let\'s prioritize it.',
            'Nice work! The code quality is excellent.',
            'I have a few suggestions for improvement.',
            'Let\'s discuss this in our next standup.',
            'Updated the documentation to reflect these changes.',
            'The performance improvements are noticeable.',
            'Should we consider a different approach here?',
        ];

        foreach (array_slice($items, 0, 8) as $item) {
            $commentCount = rand(1, 3);
            $parentCommentId = null;

            for ($i = 0; $i < $commentCount; $i++) {
                $comment = ListItemComment::create([
                    'id' => Str::uuid()->toString(),
                    'list_item_id' => $item->id,
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

    private function createListTemplates(): void
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
        ];

        foreach ($templates as $templateData) {
            ListTemplate::create([
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
                'workspace_id' => $this->workspaceId,
            ]);
        }
    }

    private function createAutomationRules(): void
    {
        $bugTemplate = ListTemplate::where('name', 'Bug Fix')->first();

        $rules = [
            [
                'name' => 'Auto-assign bugs to Logic',
                'description' => 'Automatically assign bug items to the coder agent',
                'trigger_type' => 'task_created',
                'trigger_conditions' => ['tags_contain' => 'bug'],
                'action_type' => 'assign_task',
                'action_config' => ['user_id' => 'a5'],
                'list_template_id' => $bugTemplate?->id,
                'trigger_count' => 12,
                'last_triggered_at' => now()->subDays(2),
            ],
            [
                'name' => 'High priority notification',
                'description' => 'Notify manager when high priority items are created',
                'trigger_type' => 'task_created',
                'trigger_conditions' => ['priority' => 'high'],
                'action_type' => 'send_notification',
                'action_config' => ['user_id' => 'a1', 'message' => 'New high priority item created'],
                'list_template_id' => null,
                'trigger_count' => 8,
                'last_triggered_at' => now()->subDays(1),
            ],
        ];

        foreach ($rules as $ruleData) {
            ListAutomationRule::create([
                'id' => Str::uuid()->toString(),
                'name' => $ruleData['name'],
                'description' => $ruleData['description'],
                'trigger_type' => $ruleData['trigger_type'],
                'trigger_conditions' => $ruleData['trigger_conditions'],
                'action_type' => $ruleData['action_type'],
                'action_config' => $ruleData['action_config'],
                'list_template_id' => $ruleData['list_template_id'],
                'is_active' => true,
                'trigger_count' => $ruleData['trigger_count'],
                'last_triggered_at' => $ruleData['last_triggered_at'],
                'created_by_id' => 'h1',
            ]);
        }
    }
}
