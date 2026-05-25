<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Lists\AddListItemComment;
use App\Agents\Tools\Lists\ConvertListItemToTask;
use App\Agents\Tools\Lists\CreateListItem;
use App\Agents\Tools\Lists\CreateListStatus;
use App\Agents\Tools\Lists\DeleteListItem;
use App\Agents\Tools\Lists\DeleteListItemComment;
use App\Agents\Tools\Lists\DeleteListStatus;
use App\Agents\Tools\Lists\GetListItem;
use App\Agents\Tools\Lists\ListAllItems;
use App\Agents\Tools\Lists\ListItemsByAssignee;
use App\Agents\Tools\Lists\ListItemsByStatus;
use App\Agents\Tools\Lists\ListItemStatuses;
use App\Agents\Tools\Lists\ListProjects;
use App\Agents\Tools\Lists\UpdateListItem;
use App\Agents\Tools\Lists\UpdateListStatus;
use App\Agents\Tools\Workspace\CreateAutomationRule;
use App\Agents\Tools\Workspace\CreateItemTemplate;
use App\Agents\Tools\Workspace\DeleteAutomationRule;
use App\Agents\Tools\Workspace\DeleteItemTemplate;
use App\Agents\Tools\Workspace\ListAutomationRules;
use App\Agents\Tools\Workspace\UpdateAutomationRule;
use App\Agents\Tools\Workspace\UpdateItemTemplate;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers kanban/list workflow tools.
 *
 * This group spans list items, workflow statuses, rule automation, and item
 * templates because agents experience those as one operational board surface.
 */
class ListsToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'lists';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'list, get, filter, projects, statuses, create, update, delete, comments, convert, rules, templates',
            'description' => 'Kanban board items, workflow statuses, event rules, and item templates',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:kanban';
    }

    public function tools(): array
    {
        return [
            'list_all_items' => [
                'class' => ListAllItems::class,
                'type' => 'read',
                'name' => 'List All Items',
                'description' => 'List all kanban board items, optionally filtered by parent project.',
                'icon' => 'ph:kanban',
            ],
            'get_list_item' => [
                'class' => GetListItem::class,
                'type' => 'read',
                'name' => 'Get List Item',
                'description' => 'Get full details of a single list item.',
                'icon' => 'ph:kanban',
            ],
            'list_items_by_status' => [
                'class' => ListItemsByStatus::class,
                'type' => 'read',
                'name' => 'List Items by Status',
                'description' => 'List items filtered by a specific status.',
                'icon' => 'ph:kanban',
            ],
            'list_items_by_assignee' => [
                'class' => ListItemsByAssignee::class,
                'type' => 'read',
                'name' => 'List Items by Assignee',
                'description' => 'List items assigned to a specific user.',
                'icon' => 'ph:kanban',
            ],
            'list_projects' => [
                'class' => ListProjects::class,
                'type' => 'read',
                'name' => 'List Projects',
                'description' => 'List projects (folders) on the kanban board.',
                'icon' => 'ph:kanban',
            ],
            'list_item_statuses' => [
                'class' => ListItemStatuses::class,
                'type' => 'read',
                'name' => 'List Item Statuses',
                'description' => 'List available workflow statuses and their slugs.',
                'icon' => 'ph:columns',
            ],
            'create_list_item' => [
                'class' => CreateListItem::class,
                'type' => 'write',
                'name' => 'Create List Item',
                'description' => 'Create a new list item or project/folder.',
                'icon' => 'ph:list-plus',
            ],
            'update_list_item' => [
                'class' => UpdateListItem::class,
                'type' => 'write',
                'name' => 'Update List Item',
                'description' => 'Update an existing list item.',
                'icon' => 'ph:list-plus',
            ],
            'delete_list_item' => [
                'class' => DeleteListItem::class,
                'type' => 'write',
                'name' => 'Delete List Item',
                'description' => 'Delete a list item.',
                'icon' => 'ph:list-plus',
            ],
            'add_list_item_comment' => [
                'class' => AddListItemComment::class,
                'type' => 'write',
                'name' => 'Add List Item Comment',
                'description' => 'Add a comment to a list item.',
                'icon' => 'ph:chat-teardrop-text',
            ],
            'delete_list_item_comment' => [
                'class' => DeleteListItemComment::class,
                'type' => 'write',
                'name' => 'Delete List Item Comment',
                'description' => 'Delete a comment from a list item.',
                'icon' => 'ph:chat-teardrop-text',
            ],
            'create_list_status' => [
                'class' => CreateListStatus::class,
                'type' => 'write',
                'name' => 'Create List Status',
                'description' => 'Create a new workflow status column.',
                'icon' => 'ph:columns',
            ],
            'update_list_status' => [
                'class' => UpdateListStatus::class,
                'type' => 'write',
                'name' => 'Update List Status',
                'description' => 'Update an existing workflow status.',
                'icon' => 'ph:columns',
            ],
            'delete_list_status' => [
                'class' => DeleteListStatus::class,
                'type' => 'write',
                'name' => 'Delete List Status',
                'description' => 'Delete a workflow status column.',
                'icon' => 'ph:columns',
            ],
            'convert_list_item_to_task' => [
                'class' => ConvertListItemToTask::class,
                'type' => 'write',
                'name' => 'Convert List Item to Task',
                'description' => 'Convert a list item into an agent task.',
                'icon' => 'ph:arrow-right',
            ],
            'list_automation_rules' => [
                'class' => ListAutomationRules::class,
                'type' => 'read',
                'name' => 'List Automation Rules',
                'description' => 'List all automation rules in the workspace.',
                'icon' => 'ph:lightning',
            ],
            'create_automation_rule' => [
                'class' => CreateAutomationRule::class,
                'type' => 'write',
                'name' => 'Create Automation Rule',
                'description' => 'Create a new automation rule.',
                'icon' => 'ph:lightning',
            ],
            'update_automation_rule' => [
                'class' => UpdateAutomationRule::class,
                'type' => 'write',
                'name' => 'Update Automation Rule',
                'description' => 'Update an automation rule.',
                'icon' => 'ph:lightning',
            ],
            'delete_automation_rule' => [
                'class' => DeleteAutomationRule::class,
                'type' => 'write',
                'name' => 'Delete Automation Rule',
                'description' => 'Delete an automation rule.',
                'icon' => 'ph:lightning',
            ],
            'create_item_template' => [
                'class' => CreateItemTemplate::class,
                'type' => 'write',
                'name' => 'Create Item Template',
                'description' => 'Create a list item template for automation.',
                'icon' => 'ph:copy',
            ],
            'update_item_template' => [
                'class' => UpdateItemTemplate::class,
                'type' => 'write',
                'name' => 'Update Item Template',
                'description' => 'Update a list item template.',
                'icon' => 'ph:copy',
            ],
            'delete_item_template' => [
                'class' => DeleteItemTemplate::class,
                'type' => 'write',
                'name' => 'Delete Item Template',
                'description' => 'Delete a list item template.',
                'icon' => 'ph:copy',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // Most list tools need the acting agent for workspace scoping and audit.
        // Listing automation rules is currently stateless and resolves scope in
        // the tool itself.
        return match ($class) {
            ListAutomationRules::class => new ListAutomationRules,
            ListAllItems::class,
            GetListItem::class,
            ListItemsByStatus::class,
            ListItemsByAssignee::class,
            ListProjects::class,
            ListItemStatuses::class,
            CreateListItem::class,
            UpdateListItem::class,
            DeleteListItem::class,
            AddListItemComment::class,
            DeleteListItemComment::class,
            CreateListStatus::class,
            UpdateListStatus::class,
            DeleteListStatus::class,
            ConvertListItemToTask::class,
            CreateAutomationRule::class,
            UpdateAutomationRule::class,
            DeleteAutomationRule::class,
            CreateItemTemplate::class,
            UpdateItemTemplate::class,
            DeleteItemTemplate::class => new $class($agent),
            default => throw new \RuntimeException("Unknown tool class: {$class}"),
        };
    }
}
