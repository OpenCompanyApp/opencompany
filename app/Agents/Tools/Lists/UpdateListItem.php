<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\ListItemCollaborator;
use App\Models\ListStatus;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateListItem implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update an existing list item. You can change its title, description, status, priority, assignee, parent, due date, or linked channel.';
    }

    public function handle(Request $request): string
    {
        try {
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

            if (isset($request['collaboratorIds'])) {
                ListItemCollaborator::where('list_item_id', $item->id)->delete();
                foreach ($request['collaboratorIds'] as $collaboratorId) {
                    ListItemCollaborator::create([
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'list_item_id' => $item->id,
                        'user_id' => $collaboratorId,
                    ]);
                }
            }

            $item->load(['assignee:id,name', 'creator:id,name', 'collaborators:id,name']);

            return json_encode(array_filter([
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description ?: null,
                'status' => $item->status,
                'priority' => $item->priority,
                'isFolder' => $item->is_folder ?: null,
                'parentId' => $item->parent_id,
                'assignee' => $item->assignee ? $item->assignee->name : null,
                'assigneeId' => $item->assignee_id,
                'createdBy' => $item->creator?->name,
                'dueDate' => $item->due_date?->toDateString(),
                'channelId' => $item->channel_id,
                'completedAt' => $item->completed_at?->toIso8601String(),
                'collaborators' => $item->collaborators->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->values()->toArray() ?: null,
            ], fn ($v) => $v !== null), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error updating list item: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'listItemId' => $schema
                ->string()
                ->description('The UUID of the list item to update.')
                ->required(),
            'title' => $schema
                ->string()
                ->description('The new title for the list item.'),
            'description' => $schema
                ->string()
                ->description('The new description for the list item.'),
            'status' => $schema
                ->string()
                ->description("The new status slug. Use list_item_statuses to see available statuses."),
            'priority' => $schema
                ->string()
                ->description("The new priority: 'low', 'medium', 'high', or 'urgent'."),
            'assigneeId' => $schema
                ->string()
                ->description('The UUID of the user to assign the list item to.'),
            'parentId' => $schema
                ->string()
                ->description('The UUID of a project/folder to move this item to.'),
            'dueDate' => $schema
                ->string()
                ->description('The new due date in YYYY-MM-DD format.'),
            'channelId' => $schema
                ->string()
                ->description('The UUID of the channel to link this item to.'),
            'collaboratorIds' => $schema
                ->array()
                ->description('Array of user UUIDs to set as collaborators. Replaces all existing collaborators.'),
        ];
    }
}
