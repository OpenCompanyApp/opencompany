<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListDocumentAttachments implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'List file attachments on a document, including file names, types, sizes, and URLs.';
    }

    public function handle(Request $request): string
    {
        try {
            $document = Document::forWorkspace()->findOrFail($request['documentId']);

            if (! $this->checkDocumentAccess($document)) {
                return 'Permission denied: you do not have access to this document.';
            }

            $attachments = $document->attachments()
                ->with('uploadedBy:id,name')
                ->orderBy('created_at', 'desc')
                ->get();

            return json_encode($attachments->map(fn ($a) => [
                'id' => $a->id,
                'originalName' => $a->original_name,
                'mimeType' => $a->mime_type,
                'size' => $a->size,
                'url' => $a->url,
                'uploadedBy' => $a->uploadedBy?->name ?? 'Unknown',
                'createdAt' => $a->created_at->toIso8601String(),
            ])->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing attachments: {$e->getMessage()}";
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
        ];
    }
}
