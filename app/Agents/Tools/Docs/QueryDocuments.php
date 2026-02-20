<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryDocuments implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return <<<'DESC'
Browse and query workspace documents and folders.

**Actions:**

- **list** — List documents and/or folders. Use `parentId` to browse a specific folder's contents, or omit for root level. Filter with `type`: "documents", "folders", or "all" (default).
- **get** — Get full details of a document or folder by ID. Returns content (or child listing for folders), metadata, and comment count.
- **tree** — Get a compact folder hierarchy overview with document counts. Use `parentId` to start from a subtree.
DESC;
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'list' => $this->list($request),
                'get' => $this->get($request),
                'tree' => $this->tree($request),
                default => "Unknown action: '{$action}'. Use 'list', 'get', or 'tree'.",
            };
        } catch (\Throwable $e) {
            return "Error querying documents: {$e->getMessage()}";
        }
    }

    private function list(Request $request): string
    {
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
            return $parentId
                ? 'No documents or folders found in this folder.'
                : 'No documents or folders found at root level.';
        }

        $lines = [];
        $folderLabel = $parentId ? "Folder contents" : "Root level";
        $lines[] = "{$folderLabel} ({$items->count()} items):";

        foreach ($items as $item) {
            $author = $item->author ? $item->author->name : 'Unknown';
            $updated = $item->updated_at->format('Y-m-d');

            if ($item->is_folder) {
                $lines[] = "- [Folder] {$item->title} ({$item->document_count} docs, {$item->folder_count} subfolders)";
            } else {
                $lines[] = "- {$item->title}";
            }
            $lines[] = "  ID: {$item->id} | Author: {$author} | Updated: {$updated}";
        }

        return implode("\n", $lines);
    }

    private function get(Request $request): string
    {
        $documentId = $request['documentId'] ?? null;
        if (!$documentId) {
            return "Error: 'documentId' is required for the 'get' action.";
        }

        $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

        $doc = Document::forWorkspace()
            ->with(['author:id,name', 'parent:id,title'])
            ->withCount('comments')
            ->find($documentId);

        if (!$doc) {
            return "Error: Document '{$documentId}' not found.";
        }

        // Permission check: if it's a doc in a folder, check folder access
        // If it's a folder itself, check if it's in the allowed list
        if ($allowedFolderIds !== null) {
            if ($doc->is_folder && !in_array($doc->id, $allowedFolderIds)) {
                return 'Permission denied: you do not have access to this folder.';
            }
            if (!$doc->is_folder && $doc->parent_id && !in_array($doc->parent_id, $allowedFolderIds)) {
                return 'Permission denied: you do not have access to this document.';
            }
        }

        $author = $doc->author ? $doc->author->name : 'Unknown';
        $parent = $doc->parent ? "{$doc->parent->title} (ID: {$doc->parent_id})" : 'Root';

        $lines = [
            "Title: {$doc->title}",
            "Type: " . ($doc->is_folder ? 'Folder' : 'Document'),
            "ID: {$doc->id}",
            "Author: {$author}",
            "Parent: {$parent}",
            "Created: " . $doc->created_at->format('Y-m-d H:i'),
            "Updated: " . $doc->updated_at->format('Y-m-d H:i'),
            "Comments: {$doc->comments_count}",
        ];

        if ($doc->is_system) {
            $lines[] = "System: yes (cannot be deleted)";
        }

        if ($doc->is_folder) {
            // Show folder contents summary
            $children = Document::forWorkspace()
                ->where('parent_id', $doc->id)
                ->orderBy('is_folder', 'desc')
                ->orderBy('title')
                ->take(50)
                ->get(['id', 'title', 'is_folder']);

            $folderCount = $children->where('is_folder', true)->count();
            $docCount = $children->where('is_folder', false)->count();
            $lines[] = "Contents: {$docCount} documents, {$folderCount} subfolders";

            foreach ($children as $child) {
                $prefix = $child->is_folder ? '[Folder] ' : '';
                $lines[] = "  - {$prefix}{$child->title} (ID: {$child->id})";
            }
        } else {
            // Show content snippet
            $lines[] = "Content:\n" . Str::limit($doc->content, 1000);
        }

        return implode("\n", $lines);
    }

    private function tree(Request $request): string
    {
        $parentId = $request['parentId'] ?? null;
        $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

        $query = Document::forWorkspace()
            ->where('is_folder', true)
            ->withCount([
                'children as document_count' => fn ($q) => $q->where('is_folder', false),
                'children as folder_count' => fn ($q) => $q->where('is_folder', true),
            ])
            ->orderBy('title');

        if ($allowedFolderIds !== null) {
            $query->whereIn('id', $allowedFolderIds);
        }

        $folders = $query->get();

        if ($folders->isEmpty()) {
            return 'No folders found in the workspace.';
        }

        // Build tree structure
        $byParent = $folders->groupBy('parent_id');
        $lines = ['Folder tree:'];

        $this->renderTree($byParent, $parentId, $lines, 0);

        if (count($lines) === 1) {
            // No folders matched the requested parent
            return $parentId
                ? 'No subfolders found in this folder.'
                : 'No folders found at root level.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param \Illuminate\Support\Collection<string|int, \Illuminate\Support\Collection<int, Document>> $byParent
     * @param list<string> $lines
     */
    private function renderTree($byParent, ?string $parentId, array &$lines, int $depth): void
    {
        $key = $parentId ?? '';
        $children = $byParent->get($key, collect());

        foreach ($children as $folder) {
            $indent = str_repeat('  ', $depth);
            $lines[] = "{$indent}- {$folder->title} ({$folder->document_count} docs, {$folder->folder_count} subfolders)";
            $lines[] = "{$indent}  ID: {$folder->id}";

            // Recurse into subfolders (max 5 levels deep)
            if ($depth < 5) {
                $this->renderTree($byParent, $folder->id, $lines, $depth + 1);
            }
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action: 'list' (browse documents/folders), 'get' (document details), or 'tree' (folder hierarchy).")
                ->required(),
            'documentId' => $schema
                ->string()
                ->description('The UUID of the document or folder. Required for get.'),
            'parentId' => $schema
                ->string()
                ->description('Parent folder UUID. For list: browse folder contents (omit for root). For tree: start from subtree.'),
            'type' => $schema
                ->string()
                ->description("Filter for list action: 'documents', 'folders', or 'all' (default)."),
            'limit' => $schema
                ->integer()
                ->description('Maximum items to return for list action (default: 25, max: 100).'),
        ];
    }
}
