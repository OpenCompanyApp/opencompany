<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RestoreDocumentVersion implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Restore a document to a previous version. Auto-saves the current content as a new version before restoring.';
    }

    public function handle(Request $request): string
    {
        try {
            $document = Document::forWorkspace()->findOrFail($request['documentId']);

            if (! $this->checkDocumentAccess($document)) {
                return 'Permission denied: you do not have access to this document.';
            }

            $version = DocumentVersion::where('id', $request['versionId'])
                ->where('document_id', $document->id)
                ->firstOrFail();

            // Auto-save current content before restoring
            $lastVersion = DocumentVersion::where('document_id', $document->id)
                ->max('version_number') ?? 0;

            DocumentVersion::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $document->id,
                'title' => $document->title,
                'content' => $document->content,
                'version_number' => $lastVersion + 1,
                'change_description' => 'Auto-saved before restoring version '.$version->version_number,
                'author_id' => $this->agent->id,
            ]);

            $document->update(['content' => $version->content]);

            return json_encode([
                'status' => 'restored',
                'id' => $document->id,
                'name' => $document->title,
                'title' => $document->title,
                'restoredFromVersion' => $version->version_number,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error restoring version: {$e->getMessage()}";
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
            'versionId' => $schema
                ->string()
                ->description('The UUID of the version to restore.')
                ->required(),
        ];
    }
}
