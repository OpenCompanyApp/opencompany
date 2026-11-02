<?php

namespace App\Domain\Vfs\OpenCompany\Mounts;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Base for mounts: read-only, facade-governed defaults.
 *
 * Every unsupported operation throws exactly what OpenCompanyVfs threw for a
 * root without that operation before the registry existed (not_writable for
 * mutations), so adding a mount never changes error shapes. A subclass
 * implements prefix(), description(), list() and read(), and overrides what
 * it supports. Governance stays in the facade (see VfsMount).
 */
abstract class AbstractVfsMount implements VfsMount
{
    public function isAlias(): bool
    {
        return false;
    }

    public function governsMutations(): bool
    {
        return false;
    }

    public function mutationTools(string $operation, string $path): array
    {
        return match ($operation) {
            'write' => ['vfs_write'],
            'patch' => ['vfs_patch'],
            default => [],
        };
    }

    public function entry(User $agent, array $segments, string $path): ?VfsEntry
    {
        return null;
    }

    public function pageAdapter(array $segments): ?string
    {
        return null;
    }

    public function page(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        throw new \LogicException(static::class.' declares no page adapter.');
    }

    public function readPageAdapter(array $segments): ?string
    {
        return null;
    }

    public function readPage(User $agent, array $segments, string $path, VfsBudget $budget, array $state, string $adapter): array
    {
        throw new \LogicException(static::class.' declares no read page adapter.');
    }

    public function write(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters, bool $permissionAlreadyChecked): array
    {
        throw VfsError::notWritable($path);
    }

    public function patch(User $agent, array $segments, string $path, string $patch, ?string $version, array $toolParameters): array
    {
        throw VfsError::notWritable($path);
    }

    public function remove(User $agent, array $segments, string $path, array $toolParameters): array
    {
        throw VfsError::notWritable($path);
    }

    public function move(User $agent, array $segments, string $source, string $destination, array $toolParameters): array
    {
        throw VfsError::notWritable($source);
    }

    public function makeDirectory(User $agent, array $segments, string $path, array $toolParameters): array
    {
        throw VfsError::notWritable($path);
    }

    public function searchCandidates(User $agent, string $pattern, string $path, VfsBudget $budget, bool $regex): array
    {
        return [];
    }
}
