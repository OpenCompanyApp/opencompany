<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetListItem implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Get full details of a single list item including its description, assignee, comments, collaborators, and metadata.';
    }

    public function handle(Request $request): string
    {
        try {
            $listItemId = $request['listItemId'] ?? null;
            if (!$listItemId) {
                return "Error: 'listItemId' is required.";
            }

            $item = ListItem::forWorkspace()->with(['assignee', 'collaborators', 'comments.author', 'creator'])->find($listItemId);
            if (!$item) {
                return "Error: List item '{$listItemId}' not found.";
            }

            $result = [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'status' => $item->status,
                'priority' => $item->priority,
                'assignee' => $item->assignee?->name,
                'assigneeId' => $item->assignee_id,
                'creator' => $item->creator?->name ?? 'Unknown',
                'isFolder' => $item->is_folder,
                'parentId' => $item->parent_id,
                'dueDate' => $item->due_date,
                'channelId' => $item->channel_id,
                'createdAt' => $item->created_at->toIso8601String(),
                'completedAt' => $item->completed_at?->toIso8601String(),
            ];

            if ($item->collaborators->isNotEmpty()) {
                $result['collaborators'] = $item->collaborators->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->values()->toArray();
            }

            if ($item->comments->isNotEmpty()) {
                $result['comments'] = $item->comments->take(20)->map(fn ($comment) => [
                    'id' => $comment->id,
                    'author' => $comment->author?->name ?? 'Unknown',
                    'content' => Str::limit($comment->content, 200),
                    'createdAt' => $comment->created_at->toIso8601String(),
                ])->values()->toArray();
            }

            return json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error getting list item: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'listItemId' => $schema
                ->string()
                ->description('The UUID of the list item to retrieve.')
                ->required(),
        ];
    }
}
