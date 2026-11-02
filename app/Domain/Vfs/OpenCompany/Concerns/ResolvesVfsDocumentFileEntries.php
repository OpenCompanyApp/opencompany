<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Direct entry builders for document-backed and workspace-file-backed paths.
 */
trait ResolvesVfsDocumentFileEntries
{
    /**
     * @param  list<string>  $segments
     */
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

    /**
     * @param  list<string>  $segments
     */
    private function directFileEntry(User $agent, array $segments, string $path): ?VfsEntry
    {
        if (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            return null;
        }

        try {
            $file = $this->resolveWorkspaceFilePath($agent, $path)['file'];
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return null;
            }

            throw $e;
        }

        if (! $file) {
            return null;
        }
        if (! $this->permissions->canAccessFilePath($agent, $file)) {
            throw VfsError::permissionDenied($path, 'file folder scope');
        }

        return $this->fileEntry($agent, $file, $path);
    }
}
