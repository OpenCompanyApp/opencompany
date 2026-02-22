<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\ListItemComment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteListItemComment implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a comment from a list item by its comment ID.';
    }

    public function handle(Request $request): string
    {
        try {
            $comment = ListItemComment::findOrFail($request['commentId']);
            ListItem::forWorkspace()->findOrFail($comment->list_item_id); // verify workspace
            $comment->delete();

            return 'Comment deleted.';
        } catch (\Throwable $e) {
            return "Error deleting comment: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'commentId' => $schema
                ->string()
                ->description('The UUID of the comment to delete.')
                ->required(),
        ];
    }
}
