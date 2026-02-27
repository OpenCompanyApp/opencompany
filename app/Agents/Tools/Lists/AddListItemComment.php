<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\ListItemComment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddListItemComment implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Add a comment to a list item.';
    }

    public function handle(Request $request): string
    {
        try {
            $item = ListItem::forWorkspace()->findOrFail($request['listItemId']);

            $comment = ListItemComment::create([
                'id' => Str::uuid()->toString(),
                'list_item_id' => $item->id,
                'author_id' => $this->agent->id,
                'content' => $request['commentContent'],
                'parent_id' => $request['parentId'] ?? null,
            ]);

            return json_encode(array_filter([
                'id' => $comment->id,
                'content' => $comment->content,
                'authorId' => $comment->author_id,
                'parentId' => $comment->parent_id,
            ], fn ($v) => $v !== null), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error adding comment: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'listItemId' => $schema
                ->string()
                ->description('The UUID of the list item to comment on.')
                ->required(),
            'commentContent' => $schema
                ->string()
                ->description('The content of the comment.')
                ->required(),
            'parentId' => $schema
                ->string()
                ->description('The UUID of a parent comment to reply to (for threaded comments).'),
        ];
    }
}
