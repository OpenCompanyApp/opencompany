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
                return json_encode([]);
            }

            $byParent = $folders->groupBy('parent_id');
            $tree = $this->buildTree($byParent, $parentId);

            if (empty($tree)) {
                return json_encode([]);
            }

            return json_encode($tree, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error getting document tree: {$e->getMessage()}";
        }
    }

    /**
     * @param \Illuminate\Support\Collection<string|int, \Illuminate\Support\Collection<int, Document>> $byParent
     * @return list<array<string, mixed>>
     */
    private function buildTree($byParent, ?string $parentId, int $depth = 0): array
    {
        $key = $parentId ?? '';
        $children = $byParent->get($key, collect());

        return $children->map(function ($folder) use ($byParent, $depth) {
            $node = [
                'id' => $folder->id,
                'title' => $folder->title,
                'documents' => $folder->document_count,
                'subfolders' => $folder->folder_count,
            ];

            if ($depth < 5) {
                $childNodes = $this->buildTree($byParent, $folder->id, $depth + 1);
                if (!empty($childNodes)) {
                    $node['children'] = $childNodes;
                }
            }

            return $node;
        })->values()->toArray();
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
