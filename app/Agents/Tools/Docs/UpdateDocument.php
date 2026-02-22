<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateDocument implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update an existing workspace document or folder (title, content, or parent folder).';
    }

    public function handle(Request $request): string
    {
        try {
            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

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
        } catch (\Throwable $e) {
            return "Error updating document: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'documentId' => $schema
                ->string()
                ->description('The UUID of the document to update.')
                ->required(),
            'title' => $schema
                ->string()
                ->description('The new document title.'),
            'content' => $schema
                ->string()
                ->description('The new document content.'),
            'parentId' => $schema
                ->string()
                ->description('The UUID of the new parent folder.'),
        ];
    }
}
