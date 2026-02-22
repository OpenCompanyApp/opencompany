<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AddDocumentComment implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Add a comment to a workspace document. Supports threaded replies via parentCommentId.';
    }

    public function handle(Request $request): string
    {
        try {
            $document = Document::forWorkspace()->findOrFail($request['documentId']);

            if (! $this->checkDocumentAccess($document)) {
                return 'Permission denied: you do not have access to this document.';
            }

            DocumentComment::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $document->id,
                'author_id' => $this->agent->id,
                'content' => $request['content'],
                'parent_id' => $request['parentCommentId'] ?? null,
            ]);

            return "Comment added to '{$document->title}'";
        } catch (\Throwable $e) {
            return "Error adding comment: {$e->getMessage()}";
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
                ->description('The UUID of the document to comment on.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('The comment text.')
                ->required(),
            'parentCommentId' => $schema
                ->string()
                ->description('The UUID of a parent comment for threaded replies.'),
        ];
    }
}
