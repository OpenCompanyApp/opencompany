<?php

namespace App\Agents\Tools\Files;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Services\AgentPermissionService;
use App\Services\FileSystemService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchFiles implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private FileSystemService $fileSystemService,
    ) {}

    public function description(): string
    {
        return 'Search files by name or description across all accessible folders. Optionally filter by MIME type.';
    }

    public function handle(Request $request): string
    {
        try {
            $query = $request['query'] ?? null;
            if (!$query) {
                return 'Error: query is required.';
            }

            $mimeType = $request['mimeType'] ?? null;
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

            $results = $this->fileSystemService->searchFiles($workspaceId, $query, $mimeType, null, $diskId);

            // Filter to only accessible files
            $results = $results->filter(function (WorkspaceFile $file) {
                return $this->permissionService->canAccessFilePath($this->agent, $file);
            });

            if ($results->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($results->map(fn (WorkspaceFile $file) => array_filter([
                'name' => $file->name,
                'description' => $file->description,
                'path' => $file->getVirtualPath(),
                'mimeType' => $file->mime_type,
                'size' => $file->size,
                'updatedAt' => $file->updated_at->toIso8601String(),
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error searching files: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->required()
                ->description('Search query to match against file names and descriptions.'),
            'mimeType' => $schema
                ->string()
                ->description('Filter by MIME type prefix (e.g. "text/", "application/pdf").'),
            'disk' => $schema
                ->string()
                ->description('Name or ID of the storage disk to search. Defaults to all disks. Use list_disks to see available disks.'),
        ];
    }
}
