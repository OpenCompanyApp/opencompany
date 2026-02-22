<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteDocument implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Delete a workspace document or folder. System documents cannot be deleted.';
    }

    public function handle(Request $request): string
    {
        try {
            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

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
        } catch (\Throwable $e) {
            return "Error deleting document: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'documentId' => $schema
                ->string()
                ->description('The UUID of the document to delete.')
                ->required(),
        ];
    }
}
