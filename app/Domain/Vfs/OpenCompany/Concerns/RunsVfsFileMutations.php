<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public structural VFS operations: mkdir, touch, rm, cp, mv, truncate.
 *
 * Routing: these operations started as /files-only helpers and now dispatch
 * per mount so a VFS-only agent can manage records with the same verbs:
 *
 *   rm     /files (trash), /docs (trash), /tables/…/rows (trash),
 *          /lists items (trash), /agents/{self}/notes and todo.md (trash)
 *   mv     /files (move/rename, restore from /files/.trash/), /docs
 *          (move/rename, restore from /docs/.trash/), trashed rows and list
 *          items (restore)
 *   mkdir  /files, /docs
 *
 * Delete means trash: nothing here hard-deletes. Purge is a retention concern
 * outside the VFS.
 *
 * Governance: the vfs_write primitive for these verbs is enforced by the
 * calling surface (VfsCommandExecutor composes vfs_exec + vfs_write; script
 * tools vfs_rm/vfs_mv/vfs_mkdir are evaluated as vfs_write by ToolRegistry).
 * Record operations additionally compose their domain tool here
 * (delete_document, delete_file, delete_table_row, …) via the mutation
 * policy, so an rm is never less governed than the equivalent domain tool.
 * Every public method accepts the optional `idempotency_key` through
 * $toolParameters.
 */
trait RunsVfsFileMutations
{
    /**
     * @param  array<string, mixed>|null  $toolParameters
     * @return array<string, mixed>
     */
    public function makeDirectory(User $agent, string $path, ?array $toolParameters = null): array
    {
        $this->assertBoundWorkspace($agent);
        $path = $this->routePath($agent, $path);
        $toolParameters ??= ['path' => $path];

        return $this->withIdempotency($agent, $toolParameters, 'mkdir', $path, function () use ($agent, $path, $toolParameters): array {
            $segments = $this->segments($path);
            $mount = $this->mounts()->resolve($segments) ?? throw VfsError::notWritable($path);
            $this->governMountMutation($mount, $agent, 'mkdir', $path, $toolParameters);

            return $mount->makeDirectory($agent, $segments, $path, $toolParameters);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function makeFilesDirectory(User $agent, string $path): array
    {
        $relative = $this->filesRelativePath($agent, $path);
        if ($relative === '') {
            throw VfsError::invalid('mkdir requires a path under /files or /docs.');
        }

        $existing = $this->files->resolveVirtualPath('/'.$relative, $agent->workspace_id);
        if ($existing?->is_folder) {
            if (! $this->permissions->canAccessFilePath($agent, $existing)) {
                throw VfsError::permissionDenied($path, 'file folder scope');
            }

            return [
                'status' => 'exists',
                'path' => '/files'.$existing->getVirtualPath(),
                'canonical_path' => "/files/by-id/{$existing->id}",
            ];
        }
        if ($existing) {
            throw VfsError::invalid("Path already exists and is not a directory: {$path}", ['path' => $path]);
        }

        $this->assertCanCreateFilePath($agent, $relative, $path);
        $folder = $this->files->ensureFolderPath($relative, $agent->workspace_id, $agent->id);

        return [
            'status' => 'created',
            'path' => '/files'.$folder->getVirtualPath(),
            'canonical_path' => "/files/by-id/{$folder->id}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function touch(User $agent, string $path): array
    {
        $this->assertBoundWorkspace($agent);
        $path = $this->routePath($agent, $path);
        $relative = $this->filesRelativePath($agent, $path);
        $file = $this->files->resolveVirtualPath('/'.$relative, $agent->workspace_id);

        if ($file) {
            if ($file->is_folder || ! $this->permissions->canAccessFilePath($agent, $file)) {
                throw VfsError::permissionDenied($path, 'file folder scope');
            }
            $file->touch();

            return [
                'status' => 'touched',
                'path' => '/files'.$file->getVirtualPath(),
                'canonical_path' => "/files/by-id/{$file->id}",
            ];
        }

        return $this->writeWorkspaceFile($agent, ['files', ...explode('/', $relative)], $path, '', 'create', null);
    }

    /**
     * Move one object to the trash (never a hard delete).
     *
     * @param  array<string, mixed>|null  $toolParameters
     * @return array<string, mixed>
     */
    public function remove(User $agent, string $path, ?array $toolParameters = null): array
    {
        $this->assertBoundWorkspace($agent);
        $path = $this->routePath($agent, $path);
        $toolParameters ??= ['path' => $path];
        $segments = $this->segments($path);

        if (in_array('.trash', $segments, true) || in_array('.history', $segments, true)) {
            throw VfsError::invalid('Trash and history entries cannot be removed through the VFS (purging is not a VFS operation).', ['path' => $path]);
        }

        return $this->withIdempotency($agent, $toolParameters, 'rm', $path, function () use ($agent, $segments, $path, $toolParameters): array {
            $mount = $this->mounts()->resolve($segments) ?? throw VfsError::notWritable($path);
            $this->governMountMutation($mount, $agent, 'remove', $path, $toolParameters);

            return $mount->remove($agent, $segments, $path, $toolParameters);
        });
    }

    /**
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function trashWorkspaceFilePath(User $agent, string $path, array $toolParameters): array
    {
        $file = $this->resolveFileOnlyPath($agent, $path);
        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_rm',
            operation: 'delete',
            subject: $path,
            requiredTools: ['delete_file'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Moving a file to the trash requires delete_file.',
            preview: ['kind' => $file->is_folder ? 'folder' : 'workspace_file', 'action_class' => 'delete', 'name' => $file->name, 'id' => $file->id],
            actionClass: VfsActionClass::DELETE,
        );

        $count = DB::transaction(function () use ($agent, $file, $path): int {
            $locked = WorkspaceFile::forWorkspace()->whereKey($file->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $ids = [$locked->id];
            $frontier = [$locked->id];
            for ($depth = 0; $frontier !== [] && $depth < 50; $depth++) {
                $children = WorkspaceFile::forWorkspace()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id): string => (string) $id)->all();
                array_push($ids, ...$children);
                $frontier = $children;
            }

            // Free the (workspace, parent, name) slot for new files while
            // keeping the original name and place for restore. Children keep
            // their names: their parent row is what moved out of the way.
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $metadata['trash'] = ['original_name' => $locked->name, 'original_parent_id' => $locked->parent_id];
            WorkspaceFile::withTrashed()->whereKey($locked->id)->toBase()->update([
                'name' => $locked->name.'.trashed-'.$this->idSuffix($locked->id),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            ]);
            // One fresh batch id for the whole subtree: restore selects by
            // it, never by deleted_at (second precision would sweep in a
            // child trashed on its own a moment earlier).
            WorkspaceFile::withTrashed()->whereKey($ids)->toBase()->update([
                'deleted_at' => now(),
                'deleted_by' => $agent->id,
                'trash_batch_id' => (string) Str::uuid(),
            ]);

            return count($ids);
        });

        return [
            'status' => 'trashed',
            'path' => $path,
            'canonical_path' => "/files/.trash/{$file->id}",
            'trashed' => $count,
            'restore_hint' => "mv /files/.trash/{$file->id} <destination> restores it.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function copy(User $agent, string $source, string $destination): array
    {
        $this->assertBoundWorkspace($agent);
        $source = $this->routePath($agent, $source);
        $destination = $this->routePath($agent, $destination);
        if (($this->segments($source)[0] ?? null) !== 'files' || ($this->segments($destination)[0] ?? null) !== 'files') {
            throw VfsError::unsupported('cp copies workspace files under /files. For records, read the source and vfs_write a new one.', ['source' => $source, 'destination' => $destination]);
        }
        $file = $this->resolveFileOnlyPath($agent, $source);
        if ($file->is_folder) {
            throw VfsError::unsupported('cp currently supports files, not folders.', ['path' => $source]);
        }
        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($source, 'file folder scope');
        }

        [$parentId, $name, $existing] = $this->resolveDestinationFolder($agent, $file, $destination);
        if ($existing) {
            throw VfsError::invalid("Path already exists: {$destination}", ['path' => $destination]);
        }

        $copy = $this->files->copyFile($file, $parentId, $name);

        return [
            'status' => 'copied',
            'path' => '/files'.$copy->getVirtualPath(),
            'canonical_path' => "/files/by-id/{$copy->id}",
        ];
    }

    /**
     * Move/rename, or restore from a mount's `.trash/` view.
     *
     * @param  array<string, mixed>|null  $toolParameters
     * @return array<string, mixed>
     */
    public function move(User $agent, string $source, string $destination, ?array $toolParameters = null): array
    {
        $this->assertBoundWorkspace($agent);
        $source = $this->routePath($agent, $source);
        $destination = $this->routePath($agent, $destination);
        $toolParameters ??= ['source' => $source, 'destination' => $destination];
        $segments = $this->segments($source);
        $destinationSegments = $this->segments($destination);
        $mount = $this->mounts()->resolve($segments);

        // One mount per move: both ends must resolve to the same mount prefix
        // (so nested mounts cannot be crossed either). Unmounted paths
        // compare by their first segment, as before the registry.
        $sourceKey = $mount?->prefix() ?? '/'.($segments[0] ?? '');
        $destinationKey = $this->mounts()->resolve($destinationSegments)?->prefix() ?? '/'.($destinationSegments[0] ?? '');
        if ($sourceKey !== $destinationKey) {
            throw VfsError::unsupported('mv works within one mount; read the source and vfs_write it elsewhere instead.', ['source' => $source, 'destination' => $destination]);
        }

        return $this->withIdempotency($agent, $toolParameters, 'mv', "{$source} -> {$destination}", function () use ($agent, $mount, $segments, $source, $destination, $toolParameters): array {
            if ($mount === null) {
                throw VfsError::notWritable($source);
            }
            $this->governMountMutation($mount, $agent, 'move', $source, $toolParameters);

            return $mount->move($agent, $segments, $source, $destination, $toolParameters);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function moveWorkspaceFilePath(User $agent, string $source, string $destination): array
    {
        $file = $this->resolveFileOnlyPath($agent, $source);
        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($source, 'file folder scope');
        }

        [$parentId, $name, $existing] = $this->resolveDestinationFolder($agent, $file, $destination);
        if ($existing?->id === $file->id) {
            return [
                'status' => 'unchanged',
                'path' => '/files'.$file->getVirtualPath(),
                'canonical_path' => "/files/by-id/{$file->id}",
            ];
        }
        if ($existing) {
            throw VfsError::invalid("Path already exists: {$destination}", ['path' => $destination]);
        }

        $moved = $this->files->moveFile($file, $parentId, $name);

        return [
            'status' => 'moved',
            'path' => '/files'.$moved->getVirtualPath(),
            'canonical_path' => "/files/by-id/{$moved->id}",
        ];
    }

    /**
     * Restore a trashed file or folder (and the subtree trashed with it) to
     * the destination, using the cp/mv destination rules (an existing folder
     * means "into it, keep the original name").
     *
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function restoreWorkspaceFilePath(User $agent, string $source, string $destination, array $toolParameters): array
    {
        $file = $this->resolveTrashedWorkspaceFile($agent, $source);
        $originalName = (string) ($file->metadata['trash']['original_name'] ?? $file->name);
        $probe = (clone $file)->forceFill(['name' => $originalName]);
        [$parentId, $name, $existing] = $this->resolveDestinationFolder($agent, $probe, $destination);
        if ($existing) {
            throw VfsError::invalid("Path already exists: {$destination}", [
                'path' => $destination,
                'hint' => 'Restore into another folder or under a new name.',
            ]);
        }

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_mv',
            operation: 'restore',
            subject: $source,
            requiredTools: ['delete_file'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Restoring a trashed file requires delete_file.',
            preview: ['kind' => 'workspace_file', 'action_class' => 'reversible_edit', 'name' => $originalName, 'to' => $name],
            actionClass: VfsActionClass::REVERSIBLE_EDIT,
        );

        $restored = DB::transaction(function () use ($file, $parentId, $name, $source): WorkspaceFile {
            $locked = WorkspaceFile::onlyTrashed()->where('workspace_id', $file->workspace_id)->whereKey($file->id)->lockForUpdate()->first()
                ?? throw VfsError::notFound($source);
            // Exactly the batch this root was trashed in. A descendant
            // trashed on its own earlier has another batch id (and its own
            // `.trashed-` name) and stays in the trash.
            $batchId = $locked->getAttribute('trash_batch_id');
            $ids = [$locked->id];
            $frontier = $batchId === null ? [] : [$locked->id];
            for ($depth = 0; $frontier !== [] && $depth < 50; $depth++) {
                $children = WorkspaceFile::onlyTrashed()->whereIn('parent_id', $frontier)->where('trash_batch_id', $batchId)->pluck('id')->map(fn ($id): string => (string) $id)->all();
                array_push($ids, ...$children);
                $frontier = $children;
            }

            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            unset($metadata['trash']);
            WorkspaceFile::withTrashed()->whereKey($ids)->toBase()->update(['deleted_at' => null, 'deleted_by' => null, 'trash_batch_id' => null]);
            $fresh = WorkspaceFile::forWorkspace()->findOrFail($locked->id);
            $fresh->forceFill(['parent_id' => $parentId, 'name' => $name, 'metadata' => $metadata === [] ? null : $metadata])->save();

            return $fresh->fresh() ?? $fresh;
        });

        return [
            'status' => 'restored',
            'path' => '/files'.$restored->getVirtualPath(),
            'canonical_path' => "/files/by-id/{$restored->id}",
        ];
    }

    private function resolveTrashedWorkspaceFile(User $agent, string $path): WorkspaceFile
    {
        $segments = $this->segments($path);
        $token = $segments[2] ?? throw VfsError::invalid('Name a trash entry: /files/.trash/<id>.', ['path' => $path]);
        if (isset($segments[3])) {
            throw VfsError::unsupported('Restore the trashed folder itself; its contents come back with it.', ['path' => $path]);
        }

        $query = WorkspaceFile::onlyTrashed()->where('workspace_id', $agent->workspace_id);
        $file = preg_match(self::ID_SUFFIX_PATTERN, $token, $m) === 1
            ? $query->where('id', 'like', '%'.$m[2])->first()
            : $query->whereKey($token)->first();
        if ($file === null || ! $this->canAccessTrashedFile($agent, $file)) {
            throw new VfsError('not_found', "Path not found: {$path}", ['path' => $path, 'hint' => 'ls /files/.trash to see trashed files.']);
        }

        return $file;
    }

    /**
     * Read access for a trashed file: the folder-scope rule applied at its
     * original location (its parent, which stays live for a batch root).
     */
    private function canAccessTrashedFile(User $agent, WorkspaceFile $file): bool
    {
        if ($this->permissions->getAllowedFileFolderIds($agent) === null) {
            return true;
        }

        $originalParent = $file->metadata['trash']['original_parent_id'] ?? $file->parent_id;
        if ($originalParent === null) {
            return false;
        }
        $parent = WorkspaceFile::forWorkspace()->find($originalParent);

        return $parent !== null && $this->permissions->canAccessFilePath($agent, $parent);
    }

    /**
     * @return array<string, mixed>
     */
    public function truncate(User $agent, string $path): array
    {
        $this->assertBoundWorkspace($agent);

        return $this->write($agent, $path, '', 'overwrite', permissionAlreadyChecked: true);
    }
}
