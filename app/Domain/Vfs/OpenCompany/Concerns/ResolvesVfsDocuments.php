<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\Document;

/**
 * Resolves document VFS aliases into workspace-scoped Document models.
 *
 * This concern deliberately owns only lookup and slug traversal. Mount traits
 * decide how documents appear in the filesystem, while authorization concerns
 * decide whether the resolved model may be exposed to an agent.
 */
trait ResolvesVfsDocuments
{
    private function findDocument(string $id, string $path): Document
    {
        return Document::forWorkspace()->find($id) ?? throw VfsError::notFound($path);
    }

    private function documentFromByIdSegments(array $segments, string $path): Document
    {
        $doc = $this->findDocument($segments[2], $path);
        if (! isset($segments[3])) {
            return $doc;
        }
        if (! $doc->is_folder) {
            throw VfsError::notFound($path);
        }

        return $this->resolveDocumentPathFromParent($doc->id, array_slice($segments, 3), $path);
    }

    private function resolveDocumentPath(array $parts, string $path): Document
    {
        return $this->resolveDocumentPathFromParent(null, $parts, $path);
    }

    private function resolveDocumentPathFromParent(?string $parentId, array $parts, string $path): Document
    {
        $doc = null;

        foreach ($parts as $part) {
            $doc = Document::forWorkspace()
                ->where('parent_id', $parentId)
                ->get()
                ->first(fn (Document $candidate): bool => $this->slug($candidate->title) === $part || $candidate->title === $part);

            if (! $doc) {
                throw VfsError::notFound($path);
            }

            $parentId = $doc->id;
        }

        return $doc ?? throw VfsError::notFound($path);
    }
}
