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

class CommentOnDocument implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Add, resolve, or delete comments on workspace documents.';
    }

    public function handle(Request $request): string
    {
        try {
            $action = $request['action'];

            return match ($action) {
                'add' => $this->add($request),
                'resolve' => $this->resolve($request),
                'delete' => $this->deleteComment($request),
                default => "Unknown action: {$action}. Use 'add', 'resolve', or 'delete'.",
            };
        } catch (\Throwable $e) {
            return "Error managing comment: {$e->getMessage()}";
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

    private function add(Request $request): string
    {
        $document = Document::findOrFail($request['documentId']);

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
    }

    private function resolve(Request $request): string
    {
        $comment = DocumentComment::findOrFail($request['commentId']);
        $document = Document::findOrFail($request['documentId']);

        if (! $this->checkDocumentAccess($document)) {
            return 'Permission denied: you do not have access to this document.';
        }

        $comment->update([
            'resolved' => true,
            'resolved_by_id' => $this->agent->id,
            'resolved_at' => now(),
        ]);

        return 'Comment resolved.';
    }

    private function deleteComment(Request $request): string
    {
        $comment = DocumentComment::findOrFail($request['commentId']);
        $document = Document::findOrFail($request['documentId']);

        if (! $this->checkDocumentAccess($document)) {
            return 'Permission denied: you do not have access to this document.';
        }

        $comment->delete();

        return 'Comment deleted.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("The action to perform: 'add', 'resolve', or 'delete'.")
                ->required(),
            'documentId' => $schema
                ->string()
                ->description('The UUID of the document to comment on.')
                ->required(),
            'commentId' => $schema
                ->string()
                ->description('The UUID of the comment. Required for resolve and delete.'),
            'content' => $schema
                ->string()
                ->description('The comment text. Required for add.'),
            'parentCommentId' => $schema
                ->string()
                ->description('The UUID of a parent comment for threaded replies.'),
        ];
    }
}
