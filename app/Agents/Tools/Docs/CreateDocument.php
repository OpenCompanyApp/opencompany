<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateDocument implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Create a new workspace document or folder.';
    }

    public function handle(Request $request): string
    {
        try {
            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);
            $parentId = $request['parentId'] ?? null;

            if ($parentId && $allowedFolderIds !== null && ! in_array($parentId, $allowedFolderIds)) {
                return 'Permission denied: you do not have access to the specified folder.';
            }

            $document = Document::create([
                'id' => Str::uuid()->toString(),
                'title' => $request['title'],
                'content' => $request['content'] ?? '',
                'author_id' => $this->agent->id,
                'parent_id' => $parentId,
                'is_folder' => $request['isFolder'] ?? false,
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
            ]);

            return "Document created: '{$document->title}' (ID: {$document->id})";
        } catch (\Throwable $e) {
            return "Error creating document: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema
                ->string()
                ->description('The document title.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('The document content.'),
            'parentId' => $schema
                ->string()
                ->description('The UUID of the parent folder.'),
            'isFolder' => $schema
                ->boolean()
                ->description('Whether to create a folder instead of a document.'),
        ];
    }
}
