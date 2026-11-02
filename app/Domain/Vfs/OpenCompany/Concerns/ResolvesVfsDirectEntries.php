<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Direct stat/read lookup helpers for canonical and alias VFS paths.
 *
 * This trait owns only generic direct-entry dispatch: the owning VfsMount's
 * entry() (see RegistersVfsBuiltinMounts). Resource-specific entry builders
 * live in sibling concerns so adding a mount does not turn direct lookup
 * into another cross-domain god file.
 */
trait ResolvesVfsDirectEntries
{
    private function entryForPath(User $agent, string $path): ?VfsEntry
    {
        $parent = $this->normalizePath(dirname($path));
        $name = basename($path);

        try {
            foreach ($this->list($agent, $parent) as $entry) {
                if ($entry->name === $name || $entry->path === $path || $entry->canonicalPath === $path) {
                    return $entry;
                }
            }
        } catch (VfsError) {
            return null;
        }

        return null;
    }

    private function directEntryForPath(User $agent, string $path): ?VfsEntry
    {
        $segments = $this->segments($path);

        return $this->mounts()->resolve($segments)?->entry($agent, $segments, $path);
    }
}
