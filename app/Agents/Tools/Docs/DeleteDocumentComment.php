<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteDocumentComment implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Delete a comment from a workspace document.';
    }

    public function handle(Request $request): string
    {
        try {
            $document = Document::forWorkspace()->findOrFail($request['documentId']);
            $comment = $document->comments()->findOrFail($request['commentId']);

            if (! $this->checkDocumentAccess($document)) {
                return 'Permission denied: you do not have access to this document.';
            }

            $comment->delete();

            return 'Comment deleted.';
        } catch (\Throwable $e) {
            return "Error deleting comment: {$e->getMessage()}";
        }
    }

    private function checkDocumentAccess(Document $document): bool
    {
        $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

        if ($allowedFolderIds !== null && ! in_array($document->parent_id, $allowedFolderIds)) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'documentId' => $schema
                ->string()
                ->description('The UUID of the document.')
                ->required(),
            'commentId' => $schema
                ->string()
                ->description('The UUID of the comment to delete.')
                ->required(),
        ];
    }
}
