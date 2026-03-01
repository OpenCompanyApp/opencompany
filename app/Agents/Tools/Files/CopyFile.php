<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CopyFile implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Copy a file to another location. Cannot copy folders.';
    }

    public function handle(Request $request): string
    {
        try {
            $sourcePath = $request['sourcePath'] ?? null;
            $destinationPath = $request['destinationPath'] ?? null;

            if (!$sourcePath || !$destinationPath) {
                return 'Error: sourcePath and destinationPath are required.';
            }

            $workspaceId = $this->agent->workspace_id;

            $file = $this->fileSystemService->resolveVirtualPath($sourcePath, $workspaceId);
            if (!$file) {
                return "Error: file not found at path '{$sourcePath}'.";
            }
            if ($file->is_folder) {
                return 'Error: cannot copy folders. Only files can be copied.';
            }
            if (!$this->permissionService->canAccessFilePath($this->agent, $file)) {
                return "Permission denied: you do not have access to '{$sourcePath}'.";
            }

            $destFolder = $this->fileSystemService->resolveVirtualPath($destinationPath, $workspaceId);
            if (!$destFolder || !$destFolder->is_folder) {
                return "Error: destination folder '{$destinationPath}' not found.";
            }
            if (!$this->permissionService->canAccessFilePath($this->agent, $destFolder)) {
                return "Permission denied: you do not have access to '{$destinationPath}'.";
            }

            $copy = $this->fileSystemService->copyFile($file, $destFolder->id, $request['newName'] ?? null);

            return json_encode([
                'success' => true,
                'path' => $copy->getVirtualPath(),
                'size' => $copy->size,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error copying file: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sourcePath' => $schema
                ->string()
                ->required()
                ->description('Virtual path of the file to copy.'),
            'destinationPath' => $schema
                ->string()
                ->required()
                ->description('Virtual path of the destination folder.'),
            'newName' => $schema
                ->string()
                ->description('Optional new name for the copy. Defaults to the original name.'),
        ];
    }
}
