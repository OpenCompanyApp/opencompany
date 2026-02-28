<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListDocuments implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'List documents and/or folders. Use `parentId` to browse a specific folder\'s contents, or omit for root level. Filter with `type`: "documents", "folders", or "all" (default).';
    }

    public function handle(Request $request): string
    {
        try {
            $parentId = $request['parentId'] ?? null;
            $type = $request['type'] ?? 'all';
            $limit = min($request['limit'] ?? 25, 100);
            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

            $query = Document::forWorkspace()
                ->with('author:id,name')
                ->orderBy('is_folder', 'desc') // folders first
                ->orderBy('title')
                ->take($limit);

            // Type filter
            if ($type === 'documents') {
                $query->where('is_folder', false);
            } elseif ($type === 'folders') {
                $query->where('is_folder', true);
            }

            // Add child counts for folders
            $query->withCount([
                'children as document_count' => fn ($q) => $q->where('is_folder', false),
                'children as folder_count' => fn ($q) => $q->where('is_folder', true),
            ]);

            if ($parentId) {
                // Browsing inside a specific folder
                if ($allowedFolderIds !== null && !in_array($parentId, $allowedFolderIds)) {
                    return 'Permission denied: you do not have access to this folder.';
                }
                $query->where('parent_id', $parentId);
            } else {
                // Root level
                if ($allowedFolderIds !== null) {
                    // Restricted: show only allowed folders at root + docs in allowed folders
                    $query->where(function ($q) use ($allowedFolderIds) {
                        $q->where(function ($q2) use ($allowedFolderIds) {
                            // Allowed folders themselves
                            $q2->where('is_folder', true)->whereIn('id', $allowedFolderIds);
                        })->orWhere(function ($q2) use ($allowedFolderIds) {
                            // Documents in allowed folders
                            $q2->where('is_folder', false)->whereIn('parent_id', $allowedFolderIds);
                        });
                    });
                } else {
                    $query->whereNull('parent_id');
                }
            }

            $items = $query->get();

            if ($items->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($items->map(fn ($item) => array_filter([
                'id' => $item->id,
                'name' => $item->title,
                'title' => $item->title,
                'type' => $item->is_folder ? 'folder' : 'document',
                'isFolder' => $item->is_folder,
                'author' => $item->author?->name ?? 'Unknown',
                'updatedAt' => $item->updated_at->toIso8601String(),
                'documentCount' => $item->is_folder ? $item->document_count : null,
                'folderCount' => $item->is_folder ? $item->folder_count : null,
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing documents: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'parentId' => $schema
                ->string()
                ->description('Parent folder UUID. Browse folder contents (omit for root).'),
            'type' => $schema
                ->string()
                ->description("Filter: 'documents', 'folders', or 'all' (default)."),
            'limit' => $schema
                ->integer()
                ->description('Maximum items to return (default: 25, max: 100).'),
        ];
    }
}
