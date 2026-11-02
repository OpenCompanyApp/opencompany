<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Public write and patch operations for VFS paths.
 */
trait MutatesVfsContent
{
    /**
     * @param  array<string, mixed>|null  $toolParameters
     * @param  bool  $permissionAlreadyChecked  Internal command-engine handoff only. Set this after VfsCommandExecutor has composed vfs_exec with vfs_write; never derive it from agent or tool input.
     * @return array<string, mixed>
     */
    public function write(
        User $agent,
        string $path,
        string $content,
        string $mode = 'create',
        ?string $version = null,
        ?array $toolParameters = null,
        bool $permissionAlreadyChecked = false,
    ): array {
        if (! in_array($mode, ['create', 'overwrite'], true)) {
            throw VfsError::invalid('write mode must be create or overwrite.', [
                'mode' => $mode,
                'supported_modes' => ['create', 'overwrite'],
            ]);
        }

        $path = $this->normalizePath($path);
        $segments = $this->segments($path);

        if ($segments === []) {
            throw VfsError::notWritable($path);
        }

        $toolParameters ??= array_filter([
            'path' => $path,
            'content' => $content,
            'mode' => $mode,
            'version' => $version,
        ], fn (mixed $value): bool => $value !== null);

        // Shell mutations have already passed the command engine's composed
        // vfs_exec + vfs_write policy. Every other caller is checked here so
        // direct service, replay, and background usage cannot bypass vfs_write.
        return match ($segments[0]) {
            'files' => $this->writeWorkspaceFile($agent, $segments, $path, $content, $mode, $version, $toolParameters, ! $permissionAlreadyChecked),
            default => throw VfsError::notWritable($path),
        };
    }

    /**
     * @param  array<string, mixed>|null  $toolParameters
     * @return array<string, mixed>
     */
    public function patch(User $agent, string $path, string $patch, ?string $version = null, ?array $toolParameters = null): array
    {
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);
        $toolParameters ??= array_filter([
            'path' => $path,
            'patch' => $patch,
            'version' => $version,
        ], fn (mixed $value): bool => $value !== null);

        if ($segments === []) {
            throw VfsError::notWritable($path);
        }

        $root = $segments[0];

        if ($root === 'tasks') {
            return $this->patchTask($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root === 'lists') {
            return $this->patchListItem($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root === 'tables') {
            return $this->patchTableRow($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root === 'agents') {
            return $this->patchAgentDocument($agent, $segments, $path, $patch, $version, $toolParameters);
        }

        if ($root !== 'docs' && $root !== 'files') {
            throw VfsError::notWritable($path);
        }

        if ($root === 'docs') {
            $this->resolvePatchableDocument($agent, $segments, $path);
        }

        $current = $this->read($agent, $path);
        $next = $this->applyTextPatch($current, $patch);
        $this->assertMutationToolsAllowed($agent, $path, ['vfs_patch'], $toolParameters);

        if ($root === 'docs') {
            return $this->writeDocument($agent, $segments, $path, $next, 'overwrite', $version);
        }

        // A targeted file patch is governed by vfs_patch. Calling the public
        // write surface here would incorrectly add vfs_write as a second
        // requirement, so use the already-scoped backing mutation directly.
        return $this->writeWorkspaceFile($agent, $segments, $path, $next, 'overwrite', $version);
    }
}
