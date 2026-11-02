<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use App\Models\WorkspaceFile;

/**
 * Workspace file mount operations for OpenCompanyVfs.
 *
 * Structure note for future sub-mounts: `/files` resolution goes through
 * resolveWorkspaceFilePath(); external storage will plug in as
 * `/files/drive/<connection>/…` ahead of that resolver rather than by teaching
 * WorkspaceFile about remote objects. `/files/.trash/` is the trash view
 * (restore with mv); `<file>/.history/` is the version view (RecordsVfsHistory).
 */
trait MountsVfsFiles
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listFiles(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageFiles($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Return an adapter-owned workspace-file page before cursor encoding.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function pageFiles(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        $prefix = null;
        $byIdNames = false;

        if (($segments[1] ?? null) === '.trash') {
            return $this->paginateVfsStaticEntries($this->listFileTrash($agent, $segments, $path, $budget), $budget->maxEntries, $state);
        }

        if (count($segments) === 1) {
            // by-id re-lists every file flat: an alias for recursive walkers
            // (VfsEntry::isAlias()), which reach every file by its path.
            $prefix = new VfsEntry('by-id', '/files/by-id', 'directory', capabilities: ['browse'], metadata: ['alias' => true]);
            $query = WorkspaceFile::forWorkspace()->whereNull('parent_id');
            $entryPath = fn (WorkspaceFile $file): string => '/files'.rtrim($file->getVirtualPath(), '/');
        } elseif (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            $query = WorkspaceFile::forWorkspace();
            $entryPath = fn (WorkspaceFile $file): string => "/files/by-id/{$file->id}";
            // by-id lists ids (the names that open under /files/by-id/).
            $byIdNames = true;
        } else {
            $resolved = $this->resolveWorkspaceFilePath($agent, $path);
            $folder = $resolved['file'] ?? throw VfsError::notFound($path);
            if (! $this->permissions->canAccessFilePath($agent, $folder)) {
                throw VfsError::permissionDenied($path, 'file folder scope');
            }
            if (! $folder->is_folder) {
                throw VfsError::notReadable($path);
            }

            $query = WorkspaceFile::forWorkspace()->where('parent_id', $folder->id);
            $basePath = $resolved['from_by_id']
                ? "/files/by-id/{$folder->id}"
                : $path;
            $entryPath = fn (WorkspaceFile $file): string => rtrim($basePath, '/').'/'.$file->name;
        }

        return $this->paginateVfsMountQuery(
            baseQuery: $query,
            labelColumn: 'name',
            limit: $budget->maxEntries,
            scanLimit: $budget->maxFiles,
            state: $state,
            canAccess: fn (WorkspaceFile $file): bool => $this->permissions->canAccessFilePath($agent, $file),
            toEntry: fn (WorkspaceFile $file): VfsEntry => $this->fileEntry($agent, $file, $entryPath($file), $byIdNames ? (string) $file->id : null),
            prefixEntry: $prefix,
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function readFiles(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listFiles($agent, $segments, $path, $budget));
        }

        if (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            return $this->entriesToText($this->listFiles($agent, $segments, $path, $budget));
        }

        if (($segments[1] ?? null) === '.trash') {
            if (! isset($segments[2])) {
                return $this->entriesToText($this->listFileTrash($agent, $segments, $path, $budget));
            }
            $trashed = $this->resolveTrashedWorkspaceFile($agent, $path);
            if ($trashed->is_folder || ! $this->isTextFile($trashed)) {
                throw VfsError::notReadable($path);
            }

            return VfsText::fit((string) $this->files->readFileContents($trashed), $budget, $path);
        }

        $file = $this->resolveWorkspaceFilePath($agent, $path)['file'] ?? throw VfsError::notFound($path);
        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }
        if ($file->is_folder) {
            if ($budget->isContentRead()) {
                throw VfsError::notReadable($path);
            }

            return $this->entriesToText($this->listFiles($agent, $segments, $path, $budget));
        }

        if (! $this->isTextFile($file)) {
            throw VfsError::notReadable($path);
        }

        // Read the bytes once and derive the version from that same read, so
        // vfs_read can report a version that matches the returned content.
        $content = (string) $this->files->readFileContents($file);
        $budget->recordReadVersion($this->version($file->updated_at?->toIso8601String(), $content));
        $budget->recordCanonicalPath("/files/by-id/{$file->id}");

        return VfsText::fit($content, $budget, $path);
    }

    /**
     * @param  string|null  $name  listing name; defaults to the file name
     */
    private function fileEntry(User $agent, WorkspaceFile $file, string $path, ?string $name = null): VfsEntry
    {
        $isTextFile = ! $file->is_folder && $this->isTextFile($file);
        $baseCapabilities = $file->is_folder
            ? ['browse', 'read', 'search']
            : ($isTextFile ? ['read', 'search'] : []);
        $writeDecision = $this->entryMutationDecision(
            $agent,
            $path,
            'write',
            ['vfs_write'],
            $file->is_folder
                ? 'Create files or folders below this directory with vfs_write.'
                : 'Create or overwrite this workspace file with vfs_write.',
        );
        $decisions = ['write' => $writeDecision];

        if (! $file->is_folder) {
            $decisions = [
                'patch' => $this->entryMutationDecision(
                    $agent,
                    $path,
                    'patch',
                    ['vfs_patch'],
                    'Patch this text file with vfs_patch using a targeted search/replace payload.',
                    $isTextFile,
                    'Only text-readable workspace files can be patched.',
                ),
                ...$decisions,
            ];
        }

        $metadata = [
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'updated_at' => $file->updated_at?->toIso8601String(),
            ...$this->mutationActionsMetadata($decisions),
        ];

        // Directories have one mutation surface, so retain the established flat
        // metadata contract in addition to the action-indexed representation.
        if ($file->is_folder) {
            $metadata = [...$metadata, ...$writeDecision->metadata()];
        }

        return new VfsEntry(
            name: $name ?? $file->name,
            path: $path,
            type: $file->is_folder ? 'directory' : 'file',
            canonicalPath: "/files/by-id/{$file->id}",
            backendType: 'workspace_file',
            backendId: $file->id,
            version: $this->fileVersion($file),
            capabilities: $this->mutationCapabilities($baseCapabilities, $decisions),
            metadata: $metadata,
        );
    }

    /**
     * Trashed files the agent may still see; batch roots only (a trashed
     * folder lists once and restores with its contents).
     *
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listFileTrash(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (isset($segments[2])) {
            throw VfsError::notReadable($path);
        }

        return WorkspaceFile::onlyTrashed()
            ->where('workspace_id', $agent->workspace_id)
            ->orderByDesc('deleted_at')
            ->orderBy('id')
            ->limit(max(1, $budget->maxFiles))
            ->get()
            ->filter(fn (WorkspaceFile $file): bool => is_array($file->metadata['trash'] ?? null) && $this->canAccessTrashedFile($agent, $file))
            ->take($budget->maxEntries)
            ->map(function (WorkspaceFile $file): VfsEntry {
                $original = (string) ($file->metadata['trash']['original_name'] ?? $file->name);

                return new VfsEntry(
                    name: $original.'--'.$this->idSuffix($file->id),
                    path: "/files/.trash/{$file->id}",
                    type: $file->is_folder ? 'directory' : 'file',
                    canonicalPath: "/files/.trash/{$file->id}",
                    backendType: 'workspace_file',
                    backendId: $file->id,
                    capabilities: ['read'],
                    metadata: [
                        'original_name' => $original,
                        'original_parent' => ($parent = $file->metadata['trash']['original_parent_id'] ?? null) ? "/files/by-id/{$parent}" : '/files',
                        'trashed' => true,
                        'deleted_at' => $file->deleted_at?->toIso8601String(),
                        'deleted_by' => $file->getAttribute('deleted_by'),
                        'restore_hint' => "mv /files/.trash/{$file->id} <destination> restores it.",
                    ],
                );
            })
            ->values()
            ->all();
    }

    private function isTextFile(WorkspaceFile $file): bool
    {
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        if (in_array($extension, ['txt', 'md', 'markdown', 'json', 'ndjson', 'csv', 'tsv', 'xml', 'yaml', 'yml', 'js', 'ts', 'css', 'html', 'log'], true)) {
            return true;
        }

        if ($file->mime_type === null) {
            return true;
        }

        return str_starts_with($file->mime_type, 'text/')
            || in_array($file->mime_type, ['application/json', 'application/xml', 'application/javascript', 'application/yaml'], true);
    }
}
