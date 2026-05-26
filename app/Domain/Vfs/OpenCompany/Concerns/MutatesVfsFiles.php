<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use App\Models\WorkspaceFile;

/**
 * Workspace file write, move/copy destination, and folder-scope helpers for VFS mutations.
 */
trait MutatesVfsFiles
{
    private function writeWorkspaceFile(User $agent, array $segments, string $path, string $content, string $mode, ?string $version): array
    {
        $filePath = '/'.implode('/', array_slice($segments, (($segments[1] ?? null) === 'by-id') ? 3 : 1));
        if (($segments[1] ?? null) === 'by-id' && isset($segments[2])) {
            if (isset($segments[3])) {
                throw VfsError::notFound($path);
            }
            $existing = WorkspaceFile::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
            $filePath = $existing->getVirtualPath();
        } else {
            $existing = $this->files->resolveVirtualPath($filePath, $agent->workspace_id);
        }

        if ($existing) {
            if ($existing->is_folder || ! $this->permissions->canAccessFilePath($agent, $existing)) {
                throw VfsError::permissionDenied($path, 'file folder scope');
            }
            $this->assertVersion($version, $this->fileVersion($existing), $path);
        }

        if ($mode === 'create' && $existing) {
            throw VfsError::invalid("Path already exists: {$path}", ['path' => $path]);
        }

        $trimmed = trim($filePath, '/');
        $parts = explode('/', $trimmed);
        $name = array_pop($parts);
        if (! is_string($name) || $name === '') {
            throw VfsError::invalid('File name is required.', ['path' => $path]);
        }

        $this->assertCanCreateFilePath($agent, $filePath, $path);
        $folder = null;
        if ($parts !== []) {
            $folder = $this->files->ensureFolderPath(implode('/', $parts), $agent->workspace_id, $agent->id);
            if (! $this->permissions->canAccessFilePath($agent, $folder)) {
                throw VfsError::permissionDenied($path, 'target folder scope');
            }
        }

        $file = $this->files->writeFile($agent->workspace_id, $folder?->id, $name, $content, $agent->id);

        return [
            'status' => $existing ? 'updated' : 'created',
            'path' => '/files'.$file->getVirtualPath(),
            'canonical_path' => "/files/by-id/{$file->id}",
            'version' => $this->fileVersion($file),
        ];
    }

    private function filesRelativePath(string $path): string
    {
        $segments = $this->segments($path);
        if (($segments[0] ?? null) !== 'files' || ($segments[1] ?? null) === 'by-id') {
            throw VfsError::notWritable($path);
        }

        return implode('/', array_slice($segments, 1));
    }

    private function resolveFileOnlyPath(User $agent, string $path): WorkspaceFile
    {
        $segments = $this->segments($path);
        if (($segments[0] ?? null) !== 'files') {
            throw VfsError::notWritable($path);
        }

        if (($segments[1] ?? null) === 'by-id' && isset($segments[2])) {
            if (isset($segments[3])) {
                throw VfsError::notFound($path);
            }

            return WorkspaceFile::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
        }

        return $this->files->resolveVirtualPath('/'.implode('/', array_slice($segments, 1)), $agent->workspace_id)
            ?? throw VfsError::notFound($path);
    }

    /**
     * Resolve a cp/mv destination using the unix convention where an existing
     * directory means "place the source basename inside this directory".
     *
     * @return array{0: string|null, 1: string, 2: WorkspaceFile|null}
     */
    private function resolveDestinationFolder(User $agent, WorkspaceFile $source, string $destination): array
    {
        $relative = $this->filesRelativePath($destination);
        $existing = $this->files->resolveVirtualPath('/'.$relative, $agent->workspace_id);
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
        if (! is_string($name) || $name === '') {
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
}
