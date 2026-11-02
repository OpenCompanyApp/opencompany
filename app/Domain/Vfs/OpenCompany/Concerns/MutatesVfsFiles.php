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
    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>|null  $toolParameters
     * @param  bool  $enforceWritePermission  False only for a caller that already enforced its own composed mutation policy, such as vfs_patch or VfsCommandExecutor.
     * @return array<string, mixed>
     */
    private function writeWorkspaceFile(
        User $agent,
        array $segments,
        string $path,
        string $content,
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
            $this->assertVersion($version, $this->fileVersion($existing), $path);
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
                'content' => $content,
                'mode' => $mode,
                'version' => $version,
            ], fn (mixed $value): bool => $value !== null);
            $this->assertMutationToolsAllowed(
                $agent,
                $path,
                ['vfs_write'],
                $toolParameters,
                isset($toolParameters['channelId']) && is_string($toolParameters['channelId']) ? $toolParameters['channelId'] : null,
                ['kind' => 'workspace_file', 'mode' => $mode, 'name' => $name],
                'write',
                'Raw workspace-file writes require vfs_write.',
            );
        }

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
