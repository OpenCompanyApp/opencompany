<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateListItem implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a new list item or project/folder. Items must belong to a project (parentId required). To create a project, set isFolder=true.';
    }

    public function handle(Request $request): string
    {
        try {
            $isFolder = $request['isFolder'] ?? false;
            $parentId = $request['parentId'] ?? null;
            $status = $request['status'] ?? null;

            if (!$isFolder && !$parentId) {
                return "Error: 'parentId' is required — items must belong to a project. "
                     . "Use list_projects to find existing projects, "
                     . "or create a project first with isFolder=true.";
            }

            if (!$status) {
                $status = ListStatus::forWorkspace()->where('is_default', true)->value('slug') ?? 'backlog';
            }

            // Calculate position (matching ListItemController logic)
            if ($isFolder) {
                $maxPosition = ListItem::forWorkspace()->where('parent_id', $parentId)
                    ->where('is_folder', true)
                    ->max('position') ?? 0;
            } else {
                $maxPosition = ListItem::forWorkspace()->where('parent_id', $parentId)
                    ->where('is_folder', false)
                    ->where('status', $status)
                    ->max('position') ?? 0;
            }

            $item = ListItem::create([
                'id' => Str::uuid()->toString(),
                'title' => $request['title'],
                'description' => $request['description'] ?? '',
                'status' => $status,
                'priority' => $isFolder ? 'medium' : ($request['priority'] ?? 'medium'),
                'assignee_id' => $request['assigneeId'] ?? ($isFolder ? null : $this->agent->id),
                'parent_id' => $parentId,
                'creator_id' => $this->agent->id,
                'is_folder' => $isFolder,
                'channel_id' => $request['channelId'] ?? null,
                'due_date' => $request['dueDate'] ?? null,
                'position' => $maxPosition + 1,
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
            ]);

            if ($isFolder) {
                return "Project created: '{$item->title}' (ID: {$item->id}). Use this ID as parentId when creating items in this project.";
            }

            return "List item created: '{$item->title}' (ID: {$item->id})";
        } catch (\Throwable $e) {
            return "Error creating list item: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema
                ->string()
                ->description('The list item title.')
                ->required(),
            'description' => $schema
                ->string()
                ->description('A description of the list item.'),
            'status' => $schema
                ->string()
                ->description("The status slug. Use list_item_statuses to see available statuses."),
            'priority' => $schema
                ->string()
                ->description("The list item priority: 'low', 'medium', 'high', or 'urgent'."),
            'assigneeId' => $schema
                ->string()
                ->description('The UUID of the user to assign the list item to.'),
            'parentId' => $schema
                ->string()
                ->description("The UUID of a project/folder to put this item in. Required for regular items. Use list_projects to see available projects."),
            'isFolder' => $schema
                ->boolean()
                ->description('Set to true to create a project/folder instead of a regular item.'),
            'dueDate' => $schema
                ->string()
                ->description('The due date in YYYY-MM-DD format.'),
            'channelId' => $schema
                ->string()
                ->description('The UUID of the channel to link this item to.'),
        ];
    }
}
