<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use App\Models\WorkspaceFile;

/**
 * Direct entry builders for document-backed and workspace-file-backed paths.
 */
trait ResolvesVfsDocumentFileEntries
{
    private function directDocumentEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        try {
            $doc = (($segments[1] ?? null) === 'by-id' && isset($segments[2]))
                ? $this->documentFromByIdSegments($segments, $path)
                : $this->resolveDocumentPath(array_slice($segments, 1), $path);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return null;
            }

            throw $e;
        }

        if (! $this->canAccessDocument($agent, $doc)) {
            throw VfsError::permissionDenied($path, 'document folder scope');
        }
        if ($doc->is_system) {
            return null;
        }

        return $this->documentEntry($agent, $doc, $path);
    }

    private function directFileEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (($segments[1] ?? null) === 'by-id' && isset($segments[3])) {
            return null;
        }

        $file = (($segments[1] ?? null) === 'by-id' && isset($segments[2]))
            ? WorkspaceFile::forWorkspace()->find($segments[2])
            : $this->files->resolveVirtualPath('/'.implode('/', array_slice($segments, 1)), $agent->workspace_id);

        if (! $file) {
            return null;
        }

        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }

        return $this->fileEntry($file, $path);
    }
}
