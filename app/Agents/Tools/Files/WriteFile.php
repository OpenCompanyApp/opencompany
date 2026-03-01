<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WriteFile implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Create or overwrite a text file. Parent folders are auto-created if they do not exist. Paths are relative to the workspace root.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = $request['path'] ?? null;
            $content = $request['content'] ?? '';

            if (!$path) {
                return 'Error: path is required.';
            }

            $workspaceId = $this->agent->workspace_id;
            $path = trim($path, '/');
            $segments = explode('/', $path);
            $filename = array_pop($segments);
            $folderPath = implode('/', $segments);

            // Resolve disk
            $diskId = null;
            if ($diskParam = $request['disk'] ?? null) {
                $disk = $this->fileSystemService->resolveWorkspaceDiskByNameOrId($workspaceId, $diskParam);
                if (!$disk) {
                    return "Error: disk '{$diskParam}' not found. Use list_disks to see available disks.";
                }
                $diskId = $disk->id;
            }

            // Ensure parent folder exists
            $parentId = null;
            if ($folderPath) {
                $parentFolder = $this->fileSystemService->resolveVirtualPath($folderPath, $workspaceId);
                if (!$parentFolder) {
                    // Auto-create intermediate folders
                    $parentFolder = $this->fileSystemService->ensureFolderPath($folderPath, $workspaceId, $this->agent->id, $diskId);
                }
                if (!$this->permissionService->canAccessFilePath($this->agent, $parentFolder)) {
                    return "Permission denied: you do not have access to '/{$folderPath}'.";
                }
                $parentId = $parentFolder->id;
            }

            $file = $this->fileSystemService->writeFile(
                $workspaceId,
                $parentId,
                $filename,
                $content,
                $this->agent->id,
                $request['mimeType'] ?? null,
                $diskId,
            );

            if ($request['description'] ?? null) {
                $file->update(['description' => $request['description']]);
            }

            return json_encode([
                'success' => true,
                'path' => $file->getVirtualPath(),
                'size' => $file->size,
                'mimeType' => $file->mime_type,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error writing file: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->required()
                ->description('Virtual file path (e.g. "/agents/alice/output.csv"). Parent folders are auto-created.'),
            'content' => $schema
                ->string()
                ->required()
                ->description('Text content to write to the file.'),
            'mimeType' => $schema
                ->string()
                ->description('MIME type override (auto-detected from extension by default).'),
            'description' => $schema
                ->string()
                ->description('Optional description of the file contents and purpose.'),
            'disk' => $schema
                ->string()
                ->description('Name or ID of the storage disk. Defaults to workspace default. Use list_disks to see available disks.'),
        ];
    }
}
