<?php

namespace App\Agents\Tools;

use App\Models\ListItem;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryListItems implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Query list items (kanban board items). List all items, get details, or filter by status or assignee.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'list_all' => $this->listAll($request),
                'get_item' => $this->getItem($request),
                'list_by_status' => $this->listByStatus($request),
                'list_by_assignee' => $this->listByAssignee($request),
                default => "Error: Unknown action '{$action}'. Use 'list_all', 'get_item', 'list_by_status', or 'list_by_assignee'.",
            };
        } catch (\Throwable $e) {
            return "Error querying list items: {$e->getMessage()}";
        }
    }

    private function listAll(Request $request): string
    {
        $limit = $request['limit'] ?? 25;
        $parentId = $request['parentId'] ?? null;

        $query = ListItem::with(['assignee'])
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
            $assignee = $item->assignee?->name ?? 'Unassigned';
            $priority = $item->priority ? " [{$item->priority}]" : '';
            $folder = $item->is_folder ? ' (folder)' : '';
            $lines[] = "- {$item->title} | Status: {$item->status} | Assignee: {$assignee}{$priority}{$folder}";
            $lines[] = "  ID: {$item->id}";
        }

        return implode("\n", $lines);
    }

    private function getItem(Request $request): string
    {
        $listItemId = $request['listItemId'] ?? null;
        if (!$listItemId) {
            return "Error: 'listItemId' is required for the 'get_item' action.";
        }

        $item = ListItem::with(['assignee', 'collaborators', 'comments', 'creator'])->find($listItemId);
        if (!$item) {
            return "Error: List item '{$listItemId}' not found.";
        }

        $assignee = $item->assignee?->name ?? 'Unassigned';
        $creator = $item->creator?->name ?? 'Unknown';
        $commentCount = $item->comments->count();

        $lines = [
            "Title: {$item->title}",
            "Description: " . ($item->description ?? 'None'),
            "Status: {$item->status}",
            "Priority: " . ($item->priority ?? 'None'),
            "Assignee: {$assignee}",
            "Creator: {$creator}",
            "Is Folder: " . ($item->is_folder ? 'Yes' : 'No'),
            "Parent ID: " . ($item->parent_id ?? 'None'),
            "Comments: {$commentCount}",
            "Created: " . $item->created_at->format('Y-m-d H:i'),
            "Completed: " . ($item->completed_at ? $item->completed_at->format('Y-m-d H:i') : 'Not completed'),
        ];

        if ($item->collaborators->isNotEmpty()) {
            $collabNames = $item->collaborators->pluck('name')->implode(', ');
            $lines[] = "Collaborators: {$collabNames}";
        }

        return implode("\n", $lines);
    }

    private function listByStatus(Request $request): string
    {
        $status = $request['status'] ?? null;
        if (!$status) {
            return "Error: 'status' is required for the 'list_by_status' action.";
        }

        $limit = $request['limit'] ?? 25;

        $items = ListItem::with(['assignee'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->take(min($limit, 100))
            ->get();

        if ($items->isEmpty()) {
            return "No list items found with status '{$status}'.";
        }

        $lines = ["List items with status '{$status}' ({$items->count()}):"];
        foreach ($items as $item) {
            $assignee = $item->assignee?->name ?? 'Unassigned';
            $priority = $item->priority ? " [{$item->priority}]" : '';
            $lines[] = "- {$item->title} | Assignee: {$assignee}{$priority}";
            $lines[] = "  ID: {$item->id}";
        }

        return implode("\n", $lines);
    }

    private function listByAssignee(Request $request): string
    {
        $assigneeId = $request['assigneeId'] ?? null;
        if (!$assigneeId) {
            return "Error: 'assigneeId' is required for the 'list_by_assignee' action.";
        }

        $limit = $request['limit'] ?? 25;

        $items = ListItem::with(['assignee'])
            ->where('assignee_id', $assigneeId)
            ->orderBy('created_at', 'desc')
            ->take(min($limit, 100))
            ->get();

        $assignee = User::find($assigneeId);
        $assigneeName = $assignee?->name ?? $assigneeId;

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
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The query action: 'list_all', 'get_item', 'list_by_status', or 'list_by_assignee'.")
                ->required(),
            'listItemId' => $schema
                ->string()
                ->description('The UUID of the list item. Required for get_item.'),
            'status' => $schema
                ->string()
                ->description('Filter by status. Required for list_by_status.'),
            'assigneeId' => $schema
                ->string()
                ->description('Filter by assignee user ID. Required for list_by_assignee.'),
            'parentId' => $schema
                ->string()
                ->description('Filter by parent item ID. Used with list_all to get children of a folder.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of items to return (default: 25, max: 100).'),
        ];
    }
}
