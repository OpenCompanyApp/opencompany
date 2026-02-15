<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\ListItemComment;
use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageListItem implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create, update, or delete list items and their comments. Items should be placed inside projects using parentId. Create a project first (isFolder=true), then create items with that project\'s ID as parentId.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'create' => $this->create($request),
                'update' => $this->update($request),
                'delete' => $this->delete($request),
                'add_comment' => $this->addComment($request),
                'delete_comment' => $this->deleteComment($request),
                default => "Unknown action: {$action}. Use 'create', 'update', 'delete', 'add_comment', or 'delete_comment'.",
            };
        } catch (\Throwable $e) {
            return "Error managing list item: {$e->getMessage()}";
        }
    }

    private function create(Request $request): string
    {
        $isFolder = $request['isFolder'] ?? false;
        $parentId = $request['parentId'] ?? null;
        $status = $request['status'] ?? null;

        if (!$isFolder && !$parentId) {
            return "Error: 'parentId' is required — items must belong to a project. "
                 . "Use query_list_items with action 'list_projects' to find existing projects, "
                 . "or create a project first with action 'create' and isFolder=true.";
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
    }

    private function update(Request $request): string
    {
        $item = ListItem::forWorkspace()->findOrFail($request['listItemId']);

        if (isset($request['title'])) {
            $item->title = $request['title'];
        }

        if (isset($request['description'])) {
            $item->description = $request['description'];
        }

        if (isset($request['priority'])) {
            $item->priority = $request['priority'];
        }

        if (isset($request['assigneeId'])) {
            $item->assignee_id = $request['assigneeId'];
        }

        if (isset($request['parentId'])) {
            $item->parent_id = $request['parentId'];
        }

        if (isset($request['dueDate'])) {
            $item->due_date = $request['dueDate'];
        }

        if (isset($request['channelId'])) {
            $item->channel_id = $request['channelId'];
        }

        if (isset($request['status'])) {
            $item->status = $request['status'];

            $newStatus = ListStatus::forWorkspace()->where('slug', $request['status'])->first();
            if ($newStatus) {
                if ($newStatus->is_done && !$item->completed_at) {
                    $item->completed_at = now();
                }
                if (!$newStatus->is_done && $item->completed_at) {
                    $item->completed_at = null;
                }
            }
        }

        $item->save();

        return 'List item updated.';
    }

    private function delete(Request $request): string
    {
        $item = ListItem::forWorkspace()->findOrFail($request['listItemId']);
        $title = $item->title;
        $item->delete();

        return "List item '{$title}' deleted.";
    }

    private function addComment(Request $request): string
    {
        $item = ListItem::forWorkspace()->findOrFail($request['listItemId']);

        ListItemComment::create([
            'id' => Str::uuid()->toString(),
            'list_item_id' => $item->id,
            'author_id' => $this->agent->id,
            'content' => $request['commentContent'],
        ]);

        return 'Comment added.';
    }

    private function deleteComment(Request $request): string
    {
        $comment = ListItemComment::findOrFail($request['commentId']);
        ListItem::forWorkspace()->findOrFail($comment->list_item_id); // verify workspace
        $comment->delete();

        return 'Comment deleted.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action to perform: 'create', 'update', 'delete', 'add_comment', or 'delete_comment'.")
                ->required(),
            'listItemId' => $schema
                ->string()
                ->description('The UUID of the list item. Required for all actions except create.'),
            'title' => $schema
                ->string()
                ->description('The list item title. Required for create.'),
            'description' => $schema
                ->string()
                ->description('A description of the list item.'),
            'status' => $schema
                ->string()
                ->description("The status slug. Use query_list_items with action 'list_statuses' to see available statuses."),
            'priority' => $schema
                ->string()
                ->description("The list item priority: 'low', 'medium', 'high', or 'urgent'."),
            'assigneeId' => $schema
                ->string()
                ->description('The UUID of the user to assign the list item to.'),
            'parentId' => $schema
                ->string()
                ->description("Required for items. The UUID of a project/folder to put this item in. Use query_list_items with action 'list_projects' to see available projects."),
            'isFolder' => $schema
                ->boolean()
                ->description('Set to true to create a project/folder instead of a regular item. Used with create.'),
            'dueDate' => $schema
                ->string()
                ->description('The due date in YYYY-MM-DD format.'),
            'channelId' => $schema
                ->string()
                ->description('The UUID of the channel to link this item to.'),
            'commentContent' => $schema
                ->string()
                ->description('The content of the comment. Required for add_comment.'),
            'commentId' => $schema
                ->string()
                ->description('The UUID of the comment. Required for delete_comment.'),
        ];
    }
}
