<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;

/**
 * Path normalization and root mount discovery for OpenCompanyVfs.
 */
trait ResolvesVfsPaths
{
    public function normalizePath(string $path, string $cwd = '/'): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = '.';
        }

        if (! str_starts_with($path, '/')) {
            $path = rtrim($cwd, '/').'/'.$path;
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
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
