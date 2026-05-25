<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkspaceDisk;
use App\Models\WorkspaceFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Workspace-aware file metadata and storage operations.
 *
 * WorkspaceFile records are the source of truth for hierarchy, permissions, and
 * ownership; the configured WorkspaceDisk owns physical bytes. Keep both sides
 * in sync and always scope lookups by workspace before touching storage.
 */
class FileSystemService
{
    /**
     * Resolve a WorkspaceDisk by ID, or return the workspace default.
     */
    public function resolveWorkspaceDisk(string $workspaceId, ?string $diskId = null): WorkspaceDisk
    {
        if ($diskId) {
            // Explicit disk selection is workspace-scoped so callers cannot use
            // another workspace's disk ID as a storage escape hatch.
            return WorkspaceDisk::where('workspace_id', $workspaceId)
                ->where('id', $diskId)
                ->where('enabled', true)
                ->firstOrFail();
        }

        return WorkspaceDisk::where('workspace_id', $workspaceId)
            ->where('is_default', true)
            ->firstOr(fn () => WorkspaceDisk::where('workspace_id', $workspaceId)
                ->where('enabled', true)
                ->firstOrFail());
    }

    /**
     * Resolve a WorkspaceDisk by name or ID (for agent tools).
     */
    public function resolveWorkspaceDiskByNameOrId(string $workspaceId, string $nameOrId): ?WorkspaceDisk
    {
        return WorkspaceDisk::where('workspace_id', $workspaceId)
            ->where('enabled', true)
            ->where(fn ($q) => $q->where('id', $nameOrId)->orWhere('name', $nameOrId))
            ->first();
    }

    /**
     * Get a Filesystem instance for a given workspace disk.
     */
    public function getDisk(string $workspaceId, ?string $diskId = null): Filesystem
    {
        $workspaceDisk = $this->resolveWorkspaceDisk($workspaceId, $diskId);

        return $workspaceDisk->buildFilesystem();
    }

    /**
     * Get a Filesystem for an existing file (uses the file's own disk reference).
     */
    public function getDiskForFile(WorkspaceFile $file): Filesystem
    {
        if ($file->workspace_disk_id) {
            $disk = $file->relationLoaded('workspaceDisk')
                ? $file->workspaceDisk
                : $file->workspaceDisk()->first();

            if ($disk) {
                return $disk->buildFilesystem();
            }
        }

        // Legacy fallback for files created before workspace_disk_id existed.
        // New writes should always store the WorkspaceDisk relationship.
        return Storage::disk($file->storage_disk ?? 'local');
    }

    /**
     * Resolve a virtual path like "/reports/q1.pdf" to a WorkspaceFile record.
     */
    public function resolveVirtualPath(string $virtualPath, string $workspaceId): ?WorkspaceFile
    {
        $path = trim($virtualPath, '/');
        if ($path === '') {
            return null; // Root has no WorkspaceFile record.
        }

        $segments = explode('/', $path);
        $parentId = null;

        foreach ($segments as $segment) {
            $file = WorkspaceFile::where('workspace_id', $workspaceId)
                ->where('parent_id', $parentId)
                ->where('name', $segment)
                ->first();

            if (! $file) {
                return null;
            }

            $parentId = $file->id;
        }

        return $file;
    }

    /**
     * List contents of a directory.
     */
    public function listDirectory(string $workspaceId, ?string $folderId = null, ?string $search = null, ?string $diskId = null): Collection
    {
        $query = WorkspaceFile::where('workspace_id', $workspaceId)
            ->where('parent_id', $folderId);

        if ($diskId) {
            $query->where('workspace_disk_id', $diskId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('is_folder')
            ->orderBy('name')
            ->get();
    }

    /**
     * Upload a file from an UploadedFile instance.
     */
    public function uploadFile(string $workspaceId, ?string $parentId, UploadedFile $file, string $ownerId, ?string $diskId = null): WorkspaceFile
    {
        $this->validateParentFolder($workspaceId, $parentId);

        $workspaceDisk = $this->resolveWorkspaceDisk($workspaceId, $diskId);
        $filesystem = $workspaceDisk->buildFilesystem();
        $storagePath = $this->generateStoragePath($workspaceId, $file->getClientOriginalExtension());

        $filesystem->put($storagePath, file_get_contents($file->getRealPath()));

        return WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'name' => $file->getClientOriginalName(),
            'is_folder' => false,
            'storage_disk' => $workspaceDisk->driver,
            'workspace_disk_id' => $workspaceDisk->id,
            'storage_path' => $storagePath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'owner_id' => $ownerId,
        ]);
    }

    /**
     * Write externally fetched bytes into the workspace file tree.
     *
     * Unlike uploadFile(), this path is used by trusted server-side importers
     * such as Telegram media capture where there is no UploadedFile instance.
     * The caller still supplies the workspace, destination folder, owner, MIME
     * type, and provenance metadata so the resulting WorkspaceFile remains
     * inspectable and permission-scoped like a normal upload.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function writeBinaryFile(
        string $workspaceId,
        ?string $parentId,
        string $name,
        string $contents,
        string $ownerId,
        ?string $mimeType = null,
        ?string $diskId = null,
        array $metadata = [],
    ): WorkspaceFile {
        $this->validateParentFolder($workspaceId, $parentId);

        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: 'bin';
        $workspaceDisk = $this->resolveWorkspaceDisk($workspaceId, $diskId);
        $filesystem = $workspaceDisk->buildFilesystem();
        $storagePath = $this->generateStoragePath($workspaceId, $extension);
        $filesystem->put($storagePath, $contents);

        return WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'name' => $name,
            'is_folder' => false,
            'storage_disk' => $workspaceDisk->driver,
            'workspace_disk_id' => $workspaceDisk->id,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType ?? $this->guessMimeType($extension),
            'size' => strlen($contents),
            'owner_id' => $ownerId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Write text content as a file.
     */
    public function writeFile(
        string $workspaceId,
        ?string $parentId,
        string $name,
        string $content,
        string $ownerId,
        ?string $mimeType = null,
        ?string $diskId = null,
    ): WorkspaceFile {
        $this->validateParentFolder($workspaceId, $parentId);

        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: 'txt';
        $mimeType ??= $this->guessMimeType($extension);

        // Text writes are upserts by folder/name. Existing metadata stays stable
        // while the physical bytes and MIME/size are updated in place.
        $existing = WorkspaceFile::where('workspace_id', $workspaceId)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->where('is_folder', false)
            ->first();

        if ($existing) {
            $disk = $this->getDiskForFile($existing);
            $disk->put($existing->storage_path, $content);
            $existing->update([
                'size' => strlen($content),
                'mime_type' => $mimeType,
            ]);

            return $existing->fresh();
        }

        $workspaceDisk = $this->resolveWorkspaceDisk($workspaceId, $diskId);
        $filesystem = $workspaceDisk->buildFilesystem();
        $storagePath = $this->generateStoragePath($workspaceId, $extension);
        $filesystem->put($storagePath, $content);

        return WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'name' => $name,
            'is_folder' => false,
            'storage_disk' => $workspaceDisk->driver,
            'workspace_disk_id' => $workspaceDisk->id,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'size' => strlen($content),
            'owner_id' => $ownerId,
        ]);
    }

    /**
     * Read file contents from storage.
     */
    public function readFileContents(WorkspaceFile $file): ?string
    {
        if ($file->is_folder) {
            return null;
        }

        return $this->getDiskForFile($file)->get($file->storage_path);
    }

    /**
     * Create a folder.
     */
    public function createFolder(string $workspaceId, ?string $parentId, string $name, string $ownerId, ?string $diskId = null): WorkspaceFile
    {
        $this->validateParentFolder($workspaceId, $parentId);

        // Folder creation is idempotent for tools and UI flows that ensure a
        // path before writing several files into it.
        $existing = WorkspaceFile::where('workspace_id', $workspaceId)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->where('is_folder', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        $workspaceDisk = $this->resolveWorkspaceDisk($workspaceId, $diskId);

        return WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'name' => $name,
            'is_folder' => true,
            'workspace_disk_id' => $workspaceDisk->id,
            'owner_id' => $ownerId,
        ]);
    }

    /**
     * Ensure a full folder path exists, creating intermediate folders as needed.
     * Returns the deepest folder.
     */
    public function ensureFolderPath(string $path, string $workspaceId, string $ownerId, ?string $diskId = null): WorkspaceFile
    {
        $segments = array_filter(explode('/', trim($path, '/')));
        $parentId = null;

        $folder = null;
        foreach ($segments as $segment) {
            // Reuse createFolder's idempotency for each segment so concurrent
            // callers converge on the same folder tree.
            $folder = $this->createFolder($workspaceId, $parentId, $segment, $ownerId, $diskId);
            $parentId = $folder->id;
        }

        return $folder;
    }

    /**
     * Delete a file or folder (and all descendants for folders).
     */
    public function deleteFile(WorkspaceFile $file): bool
    {
        if ($file->is_folder) {
            // Delete metadata and storage recursively so folders cannot leave
            // unreachable physical files behind.
            foreach ($file->children as $child) {
                $this->deleteFile($child);
            }
        } else {
            if ($file->storage_path) {
                $this->getDiskForFile($file)->delete($file->storage_path);
            }
        }

        return $file->delete();
    }

    /**
     * Move a file or folder to a new parent (and optionally rename).
     */
    public function moveFile(WorkspaceFile $file, ?string $newParentId, ?string $newName = null): WorkspaceFile
    {
        if ($newParentId !== null) {
            $this->validateParentFolder($file->workspace_id, $newParentId);
        }

        $file->update([
            'parent_id' => $newParentId,
            'name' => $newName ?? $file->name,
        ]);

        return $file->fresh();
    }

    /**
     * Copy a file to a new location.
     */
    public function copyFile(WorkspaceFile $file, ?string $newParentId, ?string $newName = null): WorkspaceFile
    {
        if ($file->is_folder) {
            throw new \InvalidArgumentException('Cannot copy folders.');
        }

        $this->validateParentFolder($file->workspace_id, $newParentId);

        $disk = $this->getDiskForFile($file);
        $newStoragePath = $this->generateStoragePath(
            $file->workspace_id,
            pathinfo($file->name, PATHINFO_EXTENSION),
        );

        $disk->copy($file->storage_path, $newStoragePath);

        return WorkspaceFile::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $file->workspace_id,
            'parent_id' => $newParentId,
            'name' => $newName ?? $file->name,
            'is_folder' => false,
            'storage_disk' => $file->storage_disk,
            'workspace_disk_id' => $file->workspace_disk_id,
            'storage_path' => $newStoragePath,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'owner_id' => $file->owner_id,
            'metadata' => $file->metadata,
        ]);
    }

    /**
     * Search files by name or description across the workspace.
     */
    public function searchFiles(
        string $workspaceId,
        string $query,
        ?string $mimeType = null,
        ?array $accessibleFolderIds = null,
        ?string $diskId = null,
    ): Collection {
        $dbQuery = WorkspaceFile::where('workspace_id', $workspaceId)
            ->where('is_folder', false)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });

        if ($mimeType) {
            $dbQuery->where('mime_type', 'like', "{$mimeType}%");
        }

        if ($diskId) {
            $dbQuery->where('workspace_disk_id', $diskId);
        }

        if ($accessibleFolderIds !== null) {
            $dbQuery->where(function ($q) use ($accessibleFolderIds) {
                $q->whereIn('parent_id', $accessibleFolderIds)
                    ->orWhereIn('id', $accessibleFolderIds);
            });
        }

        return $dbQuery->orderBy('name')->limit(50)->get();
    }

    /**
     * Get the folder tree structure for a workspace.
     */
    public function getFolderTree(string $workspaceId, ?string $rootId = null): array
    {
        $folders = WorkspaceFile::where('workspace_id', $workspaceId)
            ->where('is_folder', true)
            ->orderBy('name')
            ->get();

        return $this->buildTree($folders, $rootId);
    }

    /**
     * Ensure an agent's home folder exists. Lazy-creates /agents/{slug}/.
     */
    public function ensureAgentHomeFolder(User $agent): WorkspaceFile
    {
        $agentsFolder = $this->createFolder($agent->workspace_id, null, 'agents', $agent->id);

        $slug = Str::slug($agent->name);

        return $this->createFolder($agent->workspace_id, $agentsFolder->id, $slug, $agent->id);
    }

    /**
     * Get the file download URL.
     */
    public function getDownloadUrl(WorkspaceFile $file): ?string
    {
        if ($file->is_folder || ! $file->storage_path) {
            return null;
        }

        $disk = $this->getDiskForFile($file);

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($file->storage_path, now()->addMinutes(30));
            } catch (\RuntimeException) {
                // Some adapters expose temporaryUrl but throw when unsigned
                // local/private URLs are unsupported. Fall back to the API path.
            }
        }

        // For local/private disks, controllers serve downloads after normal app
        // authorization checks rather than exposing a storage URL here.
        return null;
    }

    private function generateStoragePath(string $workspaceId, string $extension): string
    {
        $uuid = Str::uuid()->toString();
        $prefix = substr($uuid, 0, 2);
        $year = now()->format('Y');
        $month = now()->format('m');

        return "{$workspaceId}/files/{$year}/{$month}/{$prefix}/{$uuid}.{$extension}";
    }

    private function validateParentFolder(string $workspaceId, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        // Parent validation is both a shape check and a workspace boundary. File
        // tools should fail here before creating metadata under an invalid tree.
        $parent = WorkspaceFile::where('workspace_id', $workspaceId)
            ->where('id', $parentId)
            ->where('is_folder', true)
            ->first();

        if (! $parent) {
            throw new \InvalidArgumentException('Parent folder not found.');
        }
    }

    private function buildTree(Collection $folders, ?string $parentId): array
    {
        return $folders
            ->where('parent_id', $parentId)
            ->map(function (WorkspaceFile $folder) use ($folders) {
                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parentId' => $folder->parent_id,
                    'children' => $this->buildTree($folders, $folder->id),
                ];
            })
            ->values()
            ->toArray();
    }

    private function guessMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'py' => 'text/x-python',
            'php' => 'text/x-php',
            'yaml', 'yml' => 'text/yaml',
            default => 'text/plain',
        };
    }
}
