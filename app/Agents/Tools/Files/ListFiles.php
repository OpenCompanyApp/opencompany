<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListFiles implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'List files and folders at a given path. Returns name, type, size, and modification date for each entry.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = $request['path'] ?? '/';
            $workspaceId = $this->agent->workspace_id;

            // Resolve disk
            $diskId = null;
            if ($diskParam = $request['disk'] ?? null) {
                $disk = $this->fileSystemService->resolveWorkspaceDiskByNameOrId($workspaceId, $diskParam);
                if (!$disk) {
                    return "Error: disk '{$diskParam}' not found. Use list_disks to see available disks.";
                }
                $diskId = $disk->id;
            }

            // Resolve path to folder
            $folderId = null;
            if ($path !== '/' && $path !== '') {
                $folder = $this->fileSystemService->resolveVirtualPath($path, $workspaceId);
                if (!$folder || !$folder->is_folder) {
                    return "Error: folder not found at path '{$path}'.";
                }
                if (!$this->permissionService->canAccessFilePath($this->agent, $folder)) {
                    return "Permission denied: you do not have access to '{$path}'.";
                }
                $folderId = $folder->id;
            }

            $items = $this->fileSystemService->listDirectory($workspaceId, $folderId, null, $diskId);

            // Filter out inaccessible items at root level
            if ($folderId === null) {
                $items = $items->filter(function (WorkspaceFile $item) {
                    return $this->permissionService->canAccessFilePath($this->agent, $item);
                });
            }

            if ($items->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($items->map(fn (WorkspaceFile $item) => array_filter([
                'name' => $item->name,
                'description' => $item->description,
                'type' => $item->is_folder ? 'folder' : 'file',
                'size' => $item->is_folder ? null : $item->size,
                'mimeType' => $item->mime_type,
                'updatedAt' => $item->updated_at->toIso8601String(),
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error listing files: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->description('Virtual folder path to list (e.g. "/reports"). Defaults to root "/".'),
            'disk' => $schema
                ->string()
                ->description('Name or ID of the storage disk. Defaults to workspace default. Use list_disks to see available disks.'),
        ];
    }
}
