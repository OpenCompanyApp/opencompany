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

            $assignee = ($item->assignee ? $item->assignee->name : 'Unassigned');
            $creator = ($item->creator ? $item->creator->name : 'Unknown');
            $commentCount = $item->comments->count();

            $lines = [
                "Title: {$item->title}",
                "Description: " . ($item->description ?: 'None'),
                "Status: {$item->status}",
                "Priority: " . ($item->priority ?? 'None'),
                "Assignee: {$assignee}",
                "Creator: {$creator}",
                "Is Folder: " . ($item->is_folder ? 'Yes' : 'No'),
                "Parent ID: " . ($item->parent_id ?? 'None'),
                "Due Date: " . ($item->due_date ?? 'None'),
                "Channel ID: " . ($item->channel_id ?? 'None'),
                "Created: " . $item->created_at->format('Y-m-d H:i'),
                "Completed: " . ($item->completed_at ? $item->completed_at->format('Y-m-d H:i') : 'Not completed'),
            ];

            if ($item->collaborators->isNotEmpty()) {
                $collabNames = $item->collaborators->pluck('name')->implode(', ');
                $lines[] = "Collaborators: {$collabNames}";
            }

            $lines[] = "Comments: {$commentCount}";
            if ($item->comments->isNotEmpty()) {
                foreach ($item->comments->take(20) as $comment) {
                    $author = ($comment->author ? $comment->author->name : 'Unknown');
                    $date = $comment->created_at->format('Y-m-d H:i');
                    $content = Str::limit($comment->content, 200);
                    $lines[] = "  [{$date}] {$author}: {$content}";
                    $lines[] = "    Comment ID: {$comment->id}";
                }
            }

            return implode("\n", $lines);
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
