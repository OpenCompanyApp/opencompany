<?php

namespace App\Agents\Tools\Lists;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListItemsByAssignee implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all items assigned to a specific user by their user ID.';
    }

    public function handle(Request $request): string
    {
        try {
            $assigneeId = $request['assigneeId'] ?? null;
            if (!$assigneeId) {
                return "Error: 'assigneeId' is required.";
            }

            $limit = $request['limit'] ?? 25;

            $items = ListItem::forWorkspace()->with(['assignee'])
                ->where('assignee_id', $assigneeId)
                ->orderBy('created_at', 'desc')
                ->take(min($limit, 100))
                ->get();

            $assignee = User::find($assigneeId);
            $assigneeName = ($assignee ? $assignee->name : $assigneeId);

            if ($items->isEmpty()) {
                return "No list items found assigned to '{$assigneeName}'.";
            }

            $lines = ["List items assigned to '{$assigneeName}' ({$items->count()}):"];
            foreach ($items as $item) {
                $priority = $item->priority ? " [{$item->priority}]" : '';
                $lines[] = "- {$item->title} | Status: {$item->status}{$priority}";
                $lines[] = "  ID: {$item->id}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error listing items by assignee: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'assigneeId' => $schema
                ->string()
                ->description('The UUID of the user to filter items by.')
                ->required(),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of items to return (default: 25, max: 100).'),
        ];
    }
}
