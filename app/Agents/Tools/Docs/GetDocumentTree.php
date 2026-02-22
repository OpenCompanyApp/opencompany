<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetDocumentTree implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Get a compact folder hierarchy overview with document counts. Use `parentId` to start from a subtree.';
    }

    public function handle(Request $request): string
    {
        try {
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
        } catch (\Throwable $e) {
            return "Error getting document tree: {$e->getMessage()}";
        }
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
            'parentId' => $schema
                ->string()
                ->description('Parent folder UUID to start from a subtree (omit for full tree).'),
        ];
    }
}
