<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListAllItems implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all list items on the kanban board, optionally filtered by parent project. Returns items ordered by most recently created.';
    }

    public function handle(Request $request): string
    {
        try {
            $limit = $request['limit'] ?? 25;
            $parentId = $request['parentId'] ?? null;

            $query = ListItem::forWorkspace()->with(['assignee'])
                ->orderBy('created_at', 'desc')
                ->take(min($limit, 100));

            if ($parentId) {
                $query->where('parent_id', $parentId);
            }

            $items = $query->get();

            if ($items->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($items->map(fn ($item) => array_filter([
                'id' => $item->id,
                'title' => $item->title,
                'status' => $item->status,
                'assignee' => $item->assignee?->name,
                'assigneeId' => $item->assignee_id,
                'priority' => $item->priority,
                'isFolder' => $item->is_folder ?: null,
                'description' => $item->description ? Str::limit($item->description, 120) : null,
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing items: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'parentId' => $schema
                ->string()
                ->description('Filter by parent item ID to get children of a specific project/folder.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of items to return (default: 25, max: 100).'),
        ];
    }
}
