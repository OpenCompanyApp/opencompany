<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteFile implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Delete a file or folder. Non-empty folders require recursive=true.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = $request['path'] ?? null;
            if (!$path) {
                return 'Error: path is required.';
            }

            $recursive = $request['recursive'] ?? false;
            $workspaceId = $this->agent->workspace_id;

            $file = $this->fileSystemService->resolveVirtualPath($path, $workspaceId);
            if (!$file) {
                return "Error: file not found at path '{$path}'.";
            }
            if (!$this->permissionService->canAccessFilePath($this->agent, $file)) {
                return "Permission denied: you do not have access to '{$path}'.";
            }

            // Non-empty folder check
            if ($file->is_folder && !$recursive && $file->children()->count() > 0) {
                return "Error: folder '{$path}' is not empty. Set recursive=true to delete it and all its contents.";
            }

            $this->fileSystemService->deleteFile($file);

            return json_encode([
                'success' => true,
                'deleted' => $path,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error deleting file: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->required()
                ->description('Virtual path of the file or folder to delete.'),
            'recursive' => $schema
                ->boolean()
                ->description('Set to true to delete non-empty folders and all their contents. Default: false.'),
        ];
    }
}
