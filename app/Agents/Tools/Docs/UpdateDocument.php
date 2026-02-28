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

class UpdateDocument implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update an existing workspace document or folder (title, content, parent folder, color, or icon). Use saveVersion to snapshot before updating.';
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
                $title = $request['title'];
                if (is_array($title)) {
                    $title = $title['name'] ?? $title['title'] ?? json_encode($title);
                }
                $document->title = (string) $title;
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

            if (isset($request['color'])) {
                $document->color = $request['color'];
            }

            if (isset($request['icon'])) {
                $document->icon = $request['icon'];
            }

            // Save current content as a version before updating
            if (($request['saveVersion'] ?? false) && $document->content) {
                $lastVersion = DocumentVersion::where('document_id', $document->id)
                    ->max('version_number') ?? 0;

                DocumentVersion::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $document->id,
                    'content' => $document->getOriginal('content'),
                    'title' => $document->getOriginal('title'),
                    'version_number' => $lastVersion + 1,
                    'change_description' => $request['changeDescription'] ?? null,
                    'author_id' => $this->agent->id,
                ]);
            }

            $document->save();

            return json_encode([
                'status' => 'updated',
                'id' => $document->id,
                'name' => $document->title,
                'title' => $document->title,
            ], JSON_PRETTY_PRINT);
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
            'color' => $schema
                ->string()
                ->description('Document color (e.g. "blue", "red", "green").'),
            'icon' => $schema
                ->string()
                ->description('Document icon identifier.'),
            'saveVersion' => $schema
                ->boolean()
                ->description('Save current content as a version before updating. Default: false.'),
            'changeDescription' => $schema
                ->string()
                ->description('Description of changes (used with saveVersion).'),
        ];
    }
}
