<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListDocumentComments implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'List comments on a document with author, threading, and resolved status. Supports filtering by resolved state.';
    }

    public function handle(Request $request): string
    {
        try {
            $document = Document::forWorkspace()->findOrFail($request['documentId']);

            if (! $this->checkDocumentAccess($document)) {
                return 'Permission denied: you do not have access to this document.';
            }

            $query = $document->comments()
                ->with(['author:id,name', 'resolvedBy:id,name'])
                ->orderBy('created_at', 'asc');

            if (isset($request['resolved'])) {
                $query->where('resolved', $request['resolved']);
            }

            $comments = $query->get();

            return json_encode($comments->map(fn ($c) => array_filter([
                'id' => $c->id,
                'author' => $c->author?->name ?? 'Unknown',
                'content' => $c->content,
                'parentId' => $c->parent_id,
                'resolved' => $c->resolved ?: null,
                'resolvedBy' => $c->resolved ? ($c->resolvedBy?->name) : null,
                'resolvedAt' => $c->resolved_at?->toIso8601String(),
                'createdAt' => $c->created_at->toIso8601String(),
            ], fn ($v) => $v !== null))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing comments: {$e->getMessage()}";
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
            'resolved' => $schema
                ->boolean()
                ->description('Filter by resolved status. Omit to list all comments.'),
        ];
    }
}
