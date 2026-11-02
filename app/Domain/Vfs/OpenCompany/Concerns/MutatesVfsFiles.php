<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Support\Facades\DB;

/**
 * Workspace file write, move/copy destination, and folder-scope helpers for VFS mutations.
 *
 * Owns the locked compare-and-write for WorkspaceFile rows, including the
 * content-hash cache and the attributed WorkspaceFileVersion snapshot of the
 * bytes each write replaces (RecordsVfsHistory). Folder scope uses
 * AgentPermissionService; workspace scope comes from forWorkspace() plus the
 * facade's assertBoundWorkspace() (which makes $agent->workspace_id and the
 * bound workspace interchangeable here).
 */
trait MutatesVfsFiles
{
    /**
     * Create or overwrite one workspace file as a locked compare-and-write.
     *
     * $content is either the bytes to write, or (for text patches) a transform
     * `fn(string $lockedContent, ?string $lockedVersion): array{content, replacements, stale_base}`
     * that is evaluated against the file as it is under the row lock. The
     * transform owns its own staleness rule (content-anchored); a raw string
     * write is strict: a supplied $version that differs from the locked
     * version fails with `conflict`, including when the file no longer exists.
     *
     * Ordering: folder-scope checks, then the (optional) vfs_write policy, then
     * the transaction. The policy may create an approval, so it must not run
     * inside the transaction.
     *
     * Atomicity: FileSystemService::writeFile puts the new bytes in a NEW
     * storage object and swaps the row's pointer inside this transaction, so
     * a failure anywhere in it (content hash, history snapshot) leaves the
     * old bytes, version and history exactly as they were. The row lock
     * serializes VFS writers on the same file.
     *
     * History: an overwrite must be able to snapshot the bytes it replaces.
     * Files above the history ceiling (config vfs.max_content_bytes) are
     * therefore not overwritable here; the error says to trash the file
     * (which keeps it restorable) and create a new one instead.
     *
     * @param  list<string>  $segments
     * @param  string|\Closure(string, ?string): array{content: string, replacements: int, stale_base: bool}  $content
     * @param  array<string, mixed>|null  $toolParameters
     * @param  bool  $enforceWritePermission  False only for a caller that already enforced its own composed mutation policy, such as vfs_patch or VfsCommandExecutor.
     * @return array<string, mixed>
     */
    private function writeWorkspaceFile(
        User $agent,
        array $segments,
        string $path,
        string|\Closure $content,
        string $mode,
        ?string $version,
        ?array $toolParameters = null,
        bool $enforceWritePermission = false,
    ): array {
        $resolved = $this->resolveWorkspaceFilePath($agent, $path);
        $filePath = '/'.$resolved['relative_path'];
        $existing = $resolved['file'];

        if ($existing) {
            if ($existing->is_folder || ! $this->permissions->canAccessFilePath($agent, $existing)) {
                throw VfsError::permissionDenied($path, 'file folder scope');
            }
            // Cheap unlocked precheck so a stale raw write fails before it can
            // create an approval. The authoritative check is under the lock.
            if (is_string($content)) {
                $this->assertVersion($version, $this->fileVersion($existing), $path);
            }
            // Also before the policy: an impossible overwrite must not create
            // an approval. Re-checked against the real bytes under the lock.
            $this->assertFileHistoryFits($existing, (int) $existing->size, $path);
        }

        if ($mode === 'create' && $existing) {
            throw VfsError::invalid("Path already exists: {$path}", ['path' => $path]);
        }

        $trimmed = trim($filePath, '/');
        $parts = explode('/', $trimmed);
        $name = array_pop($parts);
        if ($name === '') {
            throw VfsError::invalid('File name is required.', ['path' => $path]);
        }

        $this->assertCanCreateFilePath($agent, $filePath, $path);

        if ($enforceWritePermission) {
            $toolParameters ??= array_filter([
                'path' => $path,
                'content' => is_string($content) ? $content : null,
                'mode' => $mode,
                'version' => $version,
            ], fn (mixed $value): bool => $value !== null);
            $this->assertMutationToolsAllowed(
                $agent,
                $path,
                ['vfs_write'],
                $toolParameters,
                $this->channelIdFrom($toolParameters),
                ['kind' => 'workspace_file', 'mode' => $mode, 'name' => $name],
                'write',
                'Raw workspace-file writes require vfs_write.',
                VfsActionClass::REVERSIBLE_EDIT,
            );
        }

        $folder = null;
        if ($parts !== []) {
            $folder = $this->files->ensureFolderPath(implode('/', $parts), $agent->workspace_id, $agent->id);
            if (! $this->permissions->canAccessFilePath($agent, $folder)) {
                throw VfsError::permissionDenied($path, 'target folder scope');
            }
        }

        [$file, $applied] = DB::transaction(function () use ($agent, $path, $content, $mode, $version, $existing, $folder, $name): array {
            // Invariant: lock, then read, then compare, then write.
            $locked = WorkspaceFile::forWorkspace()
                ->where('parent_id', $folder?->id)
                ->where('name', $name)
                ->where('is_folder', false)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && ($locked === null || $locked->id !== $existing->id)) {
                // The file this write was planned against was deleted or
                // replaced concurrently.
                throw VfsError::conflict($path, null, $version, 'The file was removed or replaced since your read. Read it again before writing.');
            }
            if ($existing === null && $locked !== null && $mode === 'create') {
                throw VfsError::invalid("Path already exists: {$path}", ['path' => $path]);
            }

            $lockedContent = '';
            $lockedVersion = null;
            $previousBytes = null;
            if ($locked !== null) {
                if ($content instanceof \Closure && $this->isTextFile($locked)) {
                    $lockedContent = (string) $this->files->readFileContents($locked);
                    $lockedVersion = $this->version($locked->updated_at?->toIso8601String(), $lockedContent);
                    $previousBytes = $lockedContent;
                } else {
                    $lockedVersion = $this->fileVersion($locked);
                }
            }

            $applied = null;
            if ($content instanceof \Closure) {
                $applied = $content($lockedContent, $lockedVersion);
                $bytes = $applied['content'];
            } else {
                if ($version !== null && ($lockedVersion === null || ! hash_equals($lockedVersion, $version))) {
                    throw VfsError::conflict($path, $lockedVersion, $version, 'Read the file again and retry with its current version.');
                }
                $bytes = $content;
            }

            if ($locked !== null && $previousBytes === null) {
                // History needs the bytes this write replaces; refuse before
                // reading them when they are too large to keep.
                $this->assertFileHistoryFits($locked, (int) $locked->size, $path);
                $previousBytes = (string) $this->files->readFileContents($locked);
            }
            if ($locked !== null) {
                // The size column can be stale; the bytes are authoritative.
                $this->assertFileHistoryFits($locked, strlen((string) $previousBytes), $path);
            }

            $file = $this->files->writeFile($agent->workspace_id, $folder?->id, $name, $bytes, $agent->id);
            $this->recordFileContentHash($file, $bytes);

            if ($locked === null) {
                // Creation is part of the run's change set: v1 is "did not exist".
                $this->snapshotWorkspaceFileVersion($file, '', $agent, null, $this->fileVersion($file), 'VFS create');
            } elseif ($previousBytes !== $bytes) {
                $this->snapshotWorkspaceFileVersion($file, $previousBytes, $agent, $lockedVersion, $this->fileVersion($file), $content instanceof \Closure ? 'VFS patch' : 'VFS write');
            }

            return [$file, $applied];
        });

        return [
            'status' => $existing ? 'updated' : 'created',
            'path' => '/files'.$file->getVirtualPath(),
            'canonical_path' => "/files/by-id/{$file->id}",
            'version' => $this->fileVersion($file),
            ...($applied !== null ? $this->textPatchResultFields($applied) : []),
        ];
    }

    /**
     * Refuse an overwrite whose previous bytes could not be kept as a history
     * snapshot: silently losing the only copy would make an irreversible
     * change look like a reversible edit.
     *
     * @throws VfsError not_writable
     */
    private function assertFileHistoryFits(WorkspaceFile $file, int $bytes, string $path): void
    {
        $ceiling = (int) config('vfs.max_content_bytes', 20 * 1024 * 1024);
        if ($bytes <= $ceiling) {
            return;
        }

        throw new VfsError('not_writable', "{$path} is too large to overwrite through the VFS: its current content ({$bytes} bytes) cannot be kept in history.", [
            'path' => $path,
            'size' => $bytes,
            'history_limit_bytes' => $ceiling,
            'hint' => "rm it (the trash keeps the original and restores it with mv), then create the new content. Trash id: {$file->id}.",
        ]);
    }

    /**
     * Cache sha256(bytes) on the row without touching updated_at (the
     * version token is updated_at + content hash, so it must stay the value
     * writeFile just set). Done as a base query so the model's "clear a
     * stale hash" hook does not run for this deliberate update.
     */
    private function recordFileContentHash(WorkspaceFile $file, string $bytes): void
    {
        $hash = hash('sha256', $bytes);
        WorkspaceFile::withTrashed()->whereKey($file->id)->toBase()->update(['content_hash' => $hash]);
        $file->setAttribute('content_hash', $hash);
        $file->syncOriginalAttribute('content_hash');
    }

    private function filesRelativePath(User $agent, string $path): string
    {
        return $this->resolveWorkspaceFilePath($agent, $path)['relative_path'];
    }

    private function resolveFileOnlyPath(User $agent, string $path): WorkspaceFile
    {
        $file = $this->resolveWorkspaceFilePath($agent, $path)['file'];

        return $file ?? throw VfsError::notFound($path);
    }

    /**
     * Resolve a cp/mv destination using the unix convention where an existing
     * directory means "place the source basename inside this directory".
     *
     * @return array{0: string|null, 1: string, 2: WorkspaceFile|null}
     */
    private function resolveDestinationFolder(User $agent, WorkspaceFile $source, string $destination): array
    {
        $resolved = $this->resolveWorkspaceFilePath($agent, $destination);
        $relative = $resolved['relative_path'];
        $existing = $resolved['file'];
        if ($existing) {
            if (! $this->permissions->canAccessFilePath($agent, $existing)) {
                throw VfsError::permissionDenied($destination, 'target file folder scope');
            }

            if (! $existing->is_folder) {
                return [$existing->parent_id, $existing->name, $existing];
            }

            $targetPath = trim($existing->getVirtualPath(), '/').'/'.$source->name;
            $child = $this->files->resolveVirtualPath('/'.$targetPath, $agent->workspace_id);
            if ($child && ! $this->permissions->canAccessFilePath($agent, $child)) {
                throw VfsError::permissionDenied('/files/'.$targetPath, 'target file folder scope');
            }

            return [$existing->id, $source->name, $child];
        }

        $parts = explode('/', trim($relative, '/'));
        $name = array_pop($parts);
        if ($name === '') {
            throw VfsError::invalid('Destination file name is required.', ['path' => $destination]);
        }

        $this->assertCanCreateFilePath($agent, $relative, $destination);
        $folder = $parts === []
            ? null
            : $this->files->ensureFolderPath(implode('/', $parts), $agent->workspace_id, $agent->id);

        return [$folder?->id, $name, null];
    }

    private function assertCanCreateFilePath(User $agent, string $relativePath, string $displayPath): void
    {
        $allowed = $this->permissions->getAllowedFileFolderIds($agent);
        if ($allowed === null) {
            return;
        }

        $agentHomePrefix = 'agents/'.$this->slug($agent->name);
        if ($relativePath === $agentHomePrefix || str_starts_with($relativePath, $agentHomePrefix.'/')) {
            return;
        }

        $parts = explode('/', trim($relativePath, '/'));
        array_pop($parts);
        if ($parts !== []) {
            $parent = $this->files->resolveVirtualPath('/'.implode('/', $parts), $agent->workspace_id);
            if ($parent && $this->permissions->canAccessFilePath($agent, $parent)) {
                return;
            }
        }

        throw VfsError::permissionDenied($displayPath, 'target file folder scope');
    }

    /**
     * Resolve a path under /files into the real workspace-file virtual path.
     *
     * Canonical folder paths are treated as stable anchors, so
     * /files/by-id/{folder}/child resolves to the same backing path as
     * /files/{folder-display-path}/child. Exact /files/by-id/{file} still
     * addresses that single file, while children under a non-folder are invalid.
     *
     * @return array{relative_path: string, file: WorkspaceFile|null, base_folder: WorkspaceFile|null, from_by_id: bool}
     */
    private function resolveWorkspaceFilePath(User $agent, string $path): array
    {
        $segments = $this->segments($path);
        if (($segments[0] ?? null) !== 'files') {
            throw VfsError::notWritable($path);
        }

        if (($segments[1] ?? null) !== 'by-id') {
            $relative = implode('/', array_slice($segments, 1));

            return [
                'relative_path' => $relative,
                'file' => $relative === '' ? null : $this->files->resolveVirtualPath('/'.$relative, $agent->workspace_id),
                'base_folder' => null,
                'from_by_id' => false,
            ];
        }

        if (! isset($segments[2])) {
            return [
                'relative_path' => '',
                'file' => null,
                'base_folder' => null,
                'from_by_id' => true,
            ];
        }

        $base = WorkspaceFile::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
        if (! $this->permissions->canAccessFilePath($agent, $base)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }

        $tail = array_slice($segments, 3);
        if ($tail === []) {
            return [
                'relative_path' => trim($base->getVirtualPath(), '/'),
                'file' => $base,
                'base_folder' => $base->is_folder ? $base : null,
                'from_by_id' => true,
            ];
        }

        if (! $base->is_folder) {
            throw VfsError::notFound($path);
        }

        $relative = trim(trim($base->getVirtualPath(), '/').'/'.implode('/', $tail), '/');

        return [
            'relative_path' => $relative,
            'file' => $this->files->resolveVirtualPath('/'.$relative, $agent->workspace_id),
            'base_folder' => $base,
            'from_by_id' => true,
        ];
    }
}
