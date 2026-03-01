<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateFolder implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Create a folder at the given path. Intermediate folders are auto-created.';
    }

    public function handle(Request $request): string
    {
        try {
            $path = $request['path'] ?? null;
            if (!$path) {
                return 'Error: path is required.';
            }

            $workspaceId = $this->agent->workspace_id;
            $path = trim($path, '/');
            $segments = explode('/', $path);

            // Resolve disk
            $diskId = null;
            if ($diskParam = $request['disk'] ?? null) {
                $disk = $this->fileSystemService->resolveWorkspaceDiskByNameOrId($workspaceId, $diskParam);
                if (!$disk) {
                    return "Error: disk '{$diskParam}' not found. Use list_disks to see available disks.";
                }
                $diskId = $disk->id;
            }

            // Check access: if the first part of the path already exists, verify access
            $firstSegment = $segments[0];
            $existing = $this->fileSystemService->resolveVirtualPath($firstSegment, $workspaceId);
            if ($existing && !$this->permissionService->canAccessFilePath($this->agent, $existing)) {
                return "Permission denied: you do not have access to '/{$firstSegment}'.";
            }

            $folder = $this->fileSystemService->ensureFolderPath($path, $workspaceId, $this->agent->id, $diskId);

            return json_encode([
                'success' => true,
                'path' => $folder->getVirtualPath(),
                'id' => $folder->id,
            ], JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error creating folder: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->required()
                ->description('Virtual folder path to create (e.g. "/agents/alice/exports"). Intermediate folders are auto-created.'),
            'disk' => $schema
                ->string()
                ->description('Name or ID of the storage disk. Defaults to workspace default. Use list_disks to see available disks.'),
        ];
    }
}
