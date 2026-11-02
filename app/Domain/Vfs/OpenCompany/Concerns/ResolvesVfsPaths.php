<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsPath;

/**
 * Path normalization and root mount discovery for OpenCompanyVfs.
 */
trait ResolvesVfsPaths
{
    /**
     * Normalize a virtual path. Delegates to VfsPath so the approval replay
     * path (ApprovalExecutionService) normalizes subjects identically.
     */
    public function normalizePath(string $path, string $cwd = '/'): string
    {
        return VfsPath::normalize($path, $cwd);
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path, '/')), fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<VfsEntry>
     */
    private function rootEntries(): array
    {
        return collect(['agents', 'docs', 'files', 'tasks', 'lists', 'channels', 'tables', 'tools', 'automations', 'approvals', 'workspace'])
            ->map(fn (string $name): VfsEntry => new VfsEntry($name, "/{$name}", 'directory', capabilities: ['browse', 'read', 'search']))
            ->values()
            ->all();
    }
}
