<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Public VFS file mutation operations exposed by the OpenCompany adapter.
 *
 * These stay separate from the private file mutation helpers so the facade can
 * remain a small router while permission-sensitive file command behavior has a
 * clear owner.
 */
trait RunsVfsFileMutations
{
    public function makeDirectory(User $agent, string $path): array
    {
        $path = $this->normalizePath($path);
        $relative = $this->filesRelativePath($path);
        if ($relative === '') {
            throw VfsError::invalid('mkdir requires a path under /files.');
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

        $this->assertCanCreateFilePath($agent, $relative, $path);
        $folder = $this->files->ensureFolderPath($relative, $agent->workspace_id, $agent->id);

        return [
            'status' => 'created',
            'path' => '/files'.$folder->getVirtualPath(),
            'canonical_path' => "/files/by-id/{$folder->id}",
        ];
    }

    public function touch(User $agent, string $path): array
    {
        $path = $this->normalizePath($path);
        $relative = $this->filesRelativePath($path);
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

    public function remove(User $agent, string $path): array
    {
        $path = $this->normalizePath($path);
        $file = $this->resolveFileOnlyPath($agent, $path);
        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }

        $this->files->deleteFile($file);

        return ['status' => 'deleted', 'path' => $path];
    }

    public function copy(User $agent, string $source, string $destination): array
    {
        $source = $this->normalizePath($source);
        $destination = $this->normalizePath($destination);
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

    public function move(User $agent, string $source, string $destination): array
    {
        $source = $this->normalizePath($source);
        $destination = $this->normalizePath($destination);
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

    public function truncate(User $agent, string $path): array
    {
        return $this->write($agent, $path, '', 'overwrite');
    }
}
