<?php

namespace App\Agents\Tools;

use App\Models\ListItem;
use App\Models\ListItemComment;
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
        return 'Create, update, or delete list items and their comments. Use this to manage kanban board items and track work.';
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
        $item = ListItem::create([
            'id' => Str::uuid()->toString(),
            'title' => $request['title'],
            'description' => $request['description'] ?? '',
            'status' => $request['status'] ?? 'backlog',
            'priority' => $request['priority'] ?? 'medium',
            'assignee_id' => $request['assigneeId'] ?? $this->agent->id,
            'parent_id' => $request['parentId'] ?? null,
            'creator_id' => $this->agent->id,
        ]);

        return "List item created: '{$item->title}' (ID: {$item->id})";
    }

    private function update(Request $request): string
    {
        $item = ListItem::findOrFail($request['listItemId']);

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

        if (isset($request['status'])) {
            $item->status = $request['status'];

            if ($request['status'] === 'done') {
                $item->completed_at = now();
            }
        }

        $item->save();

        return 'List item updated.';
    }

    private function delete(Request $request): string
    {
        $item = ListItem::findOrFail($request['listItemId']);
        $title = $item->title;
        $item->delete();

        return "List item '{$title}' deleted.";
    }

    private function addComment(Request $request): string
    {
        $item = ListItem::findOrFail($request['listItemId']);

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
        $comment->delete();

        return 'Comment deleted.';
    }

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
                ->description("The list item status: 'backlog', 'in_progress', or 'done'."),
            'priority' => $schema
                ->string()
                ->description("The list item priority: 'low', 'medium', 'high', or 'urgent'."),
            'assigneeId' => $schema
                ->string()
                ->description('The UUID of the user to assign the list item to.'),
            'parentId' => $schema
                ->string()
                ->description('The UUID of the parent list item (for nesting).'),
            'commentContent' => $schema
                ->string()
                ->description('The content of the comment. Required for add_comment.'),
            'commentId' => $schema
                ->string()
                ->description('The UUID of the comment. Required for delete_comment.'),
        ];
    }
}
