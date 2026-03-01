<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetFileInfo implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Get metadata about a file or folder: name, size, MIME type, owner, and timestamps.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = $request['path'] ?? null;
            if (!$path) {
                return 'Error: path is required.';
            }

            $file = $this->fileSystemService->resolveVirtualPath($path, $this->agent->workspace_id);
            if (!$file) {
                return "Error: file not found at path '{$path}'.";
            }
            if (!$this->permissionService->canAccessFilePath($this->agent, $file)) {
                return "Permission denied: you do not have access to '{$path}'.";
            }

            $file->load('owner:id,name,type');

            $info = [
                'name' => $file->name,
                'description' => $file->description,
                'type' => $file->is_folder ? 'folder' : 'file',
                'path' => $file->getVirtualPath(),
                'owner' => $file->owner?->name ?? 'Unknown',
                'createdAt' => $file->created_at->toIso8601String(),
                'updatedAt' => $file->updated_at->toIso8601String(),
            ];

            if (!$file->is_folder) {
                $info['mimeType'] = $file->mime_type;
                $info['size'] = $file->size;
                $info['downloadUrl'] = $this->fileSystemService->getDownloadUrl($file);
            } else {
                $info['childCount'] = $file->children()->count();
            }

            return json_encode($info, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error getting file info: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->required()
                ->description('Virtual path to the file or folder.'),
        ];
    }
}
