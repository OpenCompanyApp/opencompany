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
                return "No list items found.";
            }

            $lines = ["List items ({$items->count()}):"];
            foreach ($items as $item) {
                $assignee = ($item->assignee ? $item->assignee->name : 'Unassigned');
                $priority = $item->priority ? " [{$item->priority}]" : '';
                $folder = $item->is_folder ? ' (folder)' : '';
                $lines[] = "- {$item->title} | Status: {$item->status} | Assignee: {$assignee}{$priority}{$folder}";
                $lines[] = "  ID: {$item->id}";
                if ($item->description) {
                    $lines[] = "  Description: " . Str::limit($item->description, 120);
                }
            }

            return implode("\n", $lines);
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
