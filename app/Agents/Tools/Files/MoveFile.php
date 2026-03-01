<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class MoveFile implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Move or rename a file or folder. Set destinationPath to move, or just newName to rename in place.';
    }

    public function handle(Request $request): string
    {
        try {
            $sourcePath = $request['sourcePath'] ?? null;
            if (!$sourcePath) {
                return 'Error: sourcePath is required.';
            }

            $workspaceId = $this->agent->workspace_id;

            $file = $this->fileSystemService->resolveVirtualPath($sourcePath, $workspaceId);
            if (!$file) {
                return "Error: file not found at path '{$sourcePath}'.";
            }
            if (!$this->permissionService->canAccessFilePath($this->agent, $file)) {
                return "Permission denied: you do not have access to '{$sourcePath}'.";
            }

            $newParentId = $file->parent_id;
            $newName = $request['newName'] ?? null;

            // If destinationPath is provided, resolve the target folder
            if (isset($request['destinationPath'])) {
                $destPath = trim($request['destinationPath'], '/');
                $destFolder = $this->fileSystemService->resolveVirtualPath($destPath, $workspaceId);
                if (!$destFolder || !$destFolder->is_folder) {
                    return "Error: destination folder '{$request['destinationPath']}' not found.";
                }
                if (!$this->permissionService->canAccessFilePath($this->agent, $destFolder)) {
                    return "Permission denied: you do not have access to '{$request['destinationPath']}'.";
                }
                $newParentId = $destFolder->id;
            }

            $result = $this->fileSystemService->moveFile($file, $newParentId, $newName);

            return json_encode([
                'success' => true,
                'path' => $result->getVirtualPath(),
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error moving file: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sourcePath' => $schema
                ->string()
                ->required()
                ->description('Current virtual path of the file or folder to move.'),
            'destinationPath' => $schema
                ->string()
                ->description('Virtual path of the target folder to move into.'),
            'newName' => $schema
                ->string()
                ->description('New name for the file or folder (for renaming).'),
        ];
    }
}
