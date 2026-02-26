<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListDocumentVersions implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'List version history for a document, showing version numbers, change descriptions, and authors.';
    }

    public function handle(Request $request): string
    {
        try {
            $document = Document::forWorkspace()->findOrFail($request['documentId']);

            if (! $this->checkDocumentAccess($document)) {
                return 'Permission denied: you do not have access to this document.';
            }

            $versions = $document->versions()
                ->with('author:id,name')
                ->orderBy('version_number', 'desc')
                ->take($request['limit'] ?? 20)
                ->get();

            return json_encode($versions->map(fn ($v) => [
                'id' => $v->id,
                'versionNumber' => $v->version_number,
                'changeDescription' => $v->change_description,
                'author' => $v->author?->name ?? 'Unknown',
                'createdAt' => $v->created_at->toIso8601String(),
            ])->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing versions: {$e->getMessage()}";
        }
    }

    private function checkDocumentAccess(Document $document): bool
    {
        $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

        if ($allowedFolderIds !== null && $document->parent_id && ! in_array($document->parent_id, $allowedFolderIds)) {
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
            'limit' => $schema
                ->integer()
                ->description('Maximum versions to return. Default: 20.'),
        ];
    }
}
