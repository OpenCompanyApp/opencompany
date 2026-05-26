<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Public write and patch operations for VFS paths.
 */
trait MutatesVfsContent
{
    public function write(User $agent, string $path, string $content, string $mode = 'create', ?string $version = null): array
    {
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

        return match ($segments[0]) {
            'files' => $this->writeWorkspaceFile($agent, $segments, $path, $content, $mode, $version),
            default => throw VfsError::notWritable($path),
        };
    }

    public function patch(User $agent, string $path, string $patch, ?string $version = null): array
    {
        $path = $this->normalizePath($path);
        $segments = $this->segments($path);

        if (($segments[0] ?? null) === 'tasks') {
            return $this->patchTask($agent, $segments, $path, $patch, $version);
        }

        if (($segments[0] ?? null) === 'lists') {
            return $this->patchListItem($agent, $segments, $path, $patch, $version);
        }

        if (($segments[0] ?? null) !== 'docs' && ($segments[0] ?? null) !== 'files') {
            throw VfsError::notWritable($path);
        }

        $current = $this->read($agent, $path);
        $next = $this->applyTextPatch($current, $patch);

        if (($segments[0] ?? null) === 'docs') {
            return $this->writeDocument($agent, $segments, $path, $next, 'overwrite', $version);
        }

        return $this->write($agent, $path, $next, 'overwrite', $version);
    }
}
