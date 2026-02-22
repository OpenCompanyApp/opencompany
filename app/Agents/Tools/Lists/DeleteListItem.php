<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteListItem implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a list item by its ID. This action is permanent.';
    }

    public function handle(Request $request): string
    {
        try {
            $item = ListItem::forWorkspace()->findOrFail($request['listItemId']);
            $title = $item->title;
            $item->delete();

            return "List item '{$title}' deleted.";
        } catch (\Throwable $e) {
            return "Error deleting list item: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'listItemId' => $schema
                ->string()
                ->description('The UUID of the list item to delete.')
                ->required(),
        ];
    }
}
