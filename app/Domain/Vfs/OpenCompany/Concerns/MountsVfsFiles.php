<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use App\Models\WorkspaceFile;

/**
 * Workspace file mount operations for OpenCompanyVfs.
 */
trait MountsVfsFiles
{
    /**
     * @return list<VfsEntry>
     */
    private function listFiles(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
            $entries = [new VfsEntry('by-id', '/files/by-id', 'directory', capabilities: ['browse'])];
            foreach ($this->files->listDirectory($agent->workspace_id, null)->take(max(0, $budget->maxEntries - count($entries))) as $item) {
                if ($this->permissions->canAccessFilePath($agent, $item)) {
                    $entries[] = $this->fileEntry($item, '/files'.rtrim($item->getVirtualPath(), '/'));
                }
            }

            return $entries;
        }

        if (($segments[1] ?? null) === 'by-id') {
            if (isset($segments[3])) {
                throw VfsError::notFound($path);
            }
            if (! isset($segments[2])) {
                return WorkspaceFile::forWorkspace()
                    ->orderByDesc('is_folder')
                    ->orderBy('name')
                    ->limit($budget->maxEntries)
                    ->get()
                    ->filter(fn (WorkspaceFile $file): bool => $this->permissions->canAccessFilePath($agent, $file))
                    ->map(fn (WorkspaceFile $file): VfsEntry => $this->fileEntry($file, "/files/by-id/{$file->id}"))
                    ->values()
                    ->all();
            }

            $file = WorkspaceFile::forWorkspace()->find($segments[2]) ?? throw VfsError::notFound($path);
        } else {
            $filePath = '/'.implode('/', array_slice($segments, 1));
            $file = $this->files->resolveVirtualPath($filePath, $agent->workspace_id) ?? throw VfsError::notFound($path);
        }

        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }
        if (! $file->is_folder) {
            throw VfsError::notReadable($path);
        }

        return $this->files->listDirectory($agent->workspace_id, $file->id)
            ->take($budget->maxEntries)
            ->filter(fn (WorkspaceFile $child): bool => $this->permissions->canAccessFilePath($agent, $child))
            ->map(fn (WorkspaceFile $child): VfsEntry => $this->fileEntry($child, rtrim($path, '/').'/'.$child->name))
            ->values()
            ->all();
    }

    private function readFiles(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listFiles($agent, $segments, $path, $budget));
        }
        if (($segments[1] ?? null) === 'by-id' && isset($segments[3])) {
            throw VfsError::notFound($path);
        }

        $file = (($segments[1] ?? null) === 'by-id' && isset($segments[2]))
            ? WorkspaceFile::forWorkspace()->find($segments[2])
            : $this->files->resolveVirtualPath('/'.implode('/', array_slice($segments, 1)), $agent->workspace_id);

        if (! $file) {
            throw VfsError::notFound($path);
        }
        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }
        if ($file->is_folder) {
            return $this->entriesToText($this->listFiles($agent, $segments, $path, $budget));
        }

        if (! $this->isTextFile($file)) {
            throw VfsError::notReadable($path);
        }

        return VfsText::truncate((string) $this->files->readFileContents($file), $budget->maxBytes);
    }

    private function fileEntry(WorkspaceFile $file, string $path): VfsEntry
    {
        return new VfsEntry(
            name: $file->name,
            path: $path,
            type: $file->is_folder ? 'directory' : 'file',
            canonicalPath: "/files/by-id/{$file->id}",
            backendType: 'workspace_file',
            backendId: $file->id,
            version: $this->fileVersion($file),
            capabilities: $file->is_folder ? ['browse', 'read', 'search', 'write'] : ['read', 'search', 'patch', 'write'],
            metadata: [
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'updated_at' => $file->updated_at?->toIso8601String(),
            ],
        );
    }

    private function isTextFile(WorkspaceFile $file): bool
    {
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        if (in_array($extension, ['txt', 'md', 'markdown', 'json', 'ndjson', 'csv', 'tsv', 'xml', 'yaml', 'yml', 'js', 'ts', 'css', 'html', 'log'], true)) {
            return true;
        }

        if ($file->mime_type === null) {
            return true;
        }

        return str_starts_with($file->mime_type, 'text/')
            || in_array($file->mime_type, ['application/json', 'application/xml', 'application/javascript', 'application/yaml'], true);
    }
}
