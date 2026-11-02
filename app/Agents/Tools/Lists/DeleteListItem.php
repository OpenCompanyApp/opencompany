<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Domain tool: move a list item (or a whole project) to the trash.
 *
 * Trash, not purge: the item stays restorable from the VFS `/lists/.trash/`
 * view. A project takes its live items with it as one trash batch
 * (TrashesInBatches), so they never stay behind as live orphans.
 */
class DeleteListItem implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a list item (or a project with its items) by its ID. It moves to the trash and can be restored.';
    }

    public function handle(Request $request): string
    {
        try {
            $item = ListItem::forWorkspace()->findOrFail($request['listItemId']);
            $title = $item->title;
            $item->trashAsBatch((string) $this->agent->id);

            return "List item '{$title}' deleted (moved to the trash; restorable).";
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
