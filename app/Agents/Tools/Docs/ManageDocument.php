<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageDocument implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Create, update, or delete workspace documents and folders.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];
            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

            return match ($action) {
                'create' => $this->create($request, $allowedFolderIds),
                'update' => $this->update($request, $allowedFolderIds),
                'delete' => $this->delete($request, $allowedFolderIds),
                default => "Unknown action: {$action}. Use 'create', 'update', or 'delete'.",
            };
        } catch (\Throwable $e) {
            return "Error managing document: {$e->getMessage()}";
        }
    }

    /** @param array<int, string>|null $allowedFolderIds */
    private function create(Request $request, ?array $allowedFolderIds): string
    {
        $parentId = $request['parentId'] ?? null;

        if ($parentId && $allowedFolderIds !== null && ! in_array($parentId, $allowedFolderIds)) {
            return 'Permission denied: you do not have access to the specified folder.';
        }

        $document = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => $request['title'],
            'content' => $request['content'] ?? '',
            'author_id' => $this->agent->id,
            'parent_id' => $parentId,
            'is_folder' => $request['isFolder'] ?? false,
            'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
        ]);

        return "Document created: '{$document->title}' (ID: {$document->id})";
    }

    /** @param array<int, string>|null $allowedFolderIds */
    private function update(Request $request, ?array $allowedFolderIds): string
    {
        $document = Document::forWorkspace()->findOrFail($request['documentId']);

        if ($allowedFolderIds !== null && ! in_array($document->parent_id, $allowedFolderIds)) {
            return 'Permission denied: you do not have access to this document.';
        }

        if (isset($request['title'])) {
            $document->title = $request['title'];
        }

        if (isset($request['content'])) {
            $document->content = $request['content'];
        }

        if (isset($request['parentId'])) {
            if ($allowedFolderIds !== null && ! in_array($request['parentId'], $allowedFolderIds)) {
                return 'Permission denied: you do not have access to the target folder.';
            }
            $document->parent_id = $request['parentId'];
        }

        $document->save();

        return "Document updated: '{$document->title}'";
    }

    /** @param array<int, string>|null $allowedFolderIds */
    private function delete(Request $request, ?array $allowedFolderIds): string
    {
        $document = Document::forWorkspace()->findOrFail($request['documentId']);

        if ($document->is_system) {
            return 'This is a system document and cannot be deleted.';
        }

        if ($allowedFolderIds !== null && ! in_array($document->parent_id, $allowedFolderIds)) {
            return 'Permission denied: you do not have access to this document.';
        }

        $title = $document->title;
        $document->delete();

        return "Document deleted: '{$title}'";
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action to perform: 'create', 'update', or 'delete'.")
                ->required(),
            'documentId' => $schema
                ->string()
                ->description('The UUID of the document. Required for update and delete.'),
            'title' => $schema
                ->string()
                ->description('The document title. Required for create.'),
            'content' => $schema
                ->string()
                ->description('The document content.'),
            'parentId' => $schema
                ->string()
                ->description('The UUID of the parent folder.'),
            'isFolder' => $schema
                ->boolean()
                ->description('Whether to create a folder instead of a document.'),
        ];
    }
}
