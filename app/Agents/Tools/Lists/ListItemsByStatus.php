<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListItemsByStatus implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all items that have a specific status. Use list_item_statuses first to see available status slugs.';
    }

    public function handle(Request $request): string
    {
        try {
            $status = $request['status'] ?? null;
            if (!$status) {
                return "Error: 'status' is required.";
            }

            $limit = $request['limit'] ?? 25;

            $items = ListItem::forWorkspace()->with(['assignee'])
                ->where('status', $status)
                ->orderBy('created_at', 'desc')
                ->take(min($limit, 100))
                ->get();

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
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing items by status: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema
                ->string()
                ->description('The status slug to filter by.')
                ->required(),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of items to return (default: 25, max: 100).'),
        ];
    }
}
