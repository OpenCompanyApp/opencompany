<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves document VFS aliases into workspace-scoped Document models.
 *
 * This concern deliberately owns only lookup and slug traversal. Mount traits
 * decide how documents appear in the filesystem, while authorization concerns
 * decide whether the resolved model may be exposed to an agent.
 *
 * Naming contract (see ResolvesVfsFriendlyNames): a segment is the slug of the
 * title, `{slug}--{id8}` when siblings collide, the exact title, or any of
 * those plus `.md`. /docs paths never resolve to system documents (agent
 * identity/memory trees); those are reachable only through /agents and /me,
 * so a user folder named like a hidden system folder never collides with it.
 */
trait ResolvesVfsDocuments
{
    private function findDocument(string $id, string $path): Document
    {
        return Document::forWorkspace()->find($id) ?? throw VfsError::notFound($path);
    }

    /**
     * @param  list<string>  $segments
     * @param  User  $viewer  the acting agent; errors only ever name documents it may read
     */
    private function documentFromByIdSegments(User $viewer, array $segments, string $path): Document
    {
        $doc = $this->findDocument($segments[2], $path);
        if (! isset($segments[3])) {
            return $doc;
        }
        if (! $doc->is_folder) {
            throw VfsError::notFound($path);
        }

        return $this->resolveDocumentPathFromParent($doc->id, array_slice($segments, 3), $path, "/docs/by-id/{$doc->id}", ! $doc->is_system, $viewer);
    }

    /**
     * @param  list<string>  $parts
     * @param  User  $viewer  the acting agent; errors only ever name documents it may read
     */
    private function resolveDocumentPath(User $viewer, array $parts, string $path): Document
    {
        return $this->resolveDocumentPathFromParent(null, $parts, $path, '/docs', true, $viewer);
    }

    /**
     * Walk friendly segments from a parent folder (null = workspace root).
     *
     * Visibility: with a $viewer, not_found suggestions and ambiguity lists at
     * every segment (including intermediate folders) contain only documents
     * the viewer may read (AuthorizesVfsDocuments::canAccessDocument over the
     * full chain), so children of a hidden folder are never suggested either.
     * The returned document itself is NOT authorized here; callers still
     * apply their read/write checks to it.
     *
     * @param  list<string>  $parts
     * @param  bool  $excludeSystem  true for /docs paths; false inside agent trees
     * @param  User|null  $viewer  null only for agent trees, whose access is decided by the /agents mount
     *
     * @throws VfsError not_found (with did_you_mean) or ambiguous_match
     */
    private function resolveDocumentPathFromParent(?string $parentId, array $parts, string $path, string $basePath = '/docs', bool $excludeSystem = false, ?User $viewer = null): Document
    {
        $doc = null;
        $parentPath = $basePath;
        $visible = $viewer === null
            ? null
            : fn (Model $candidate): bool => $candidate instanceof Document && $this->canAccessDocument($viewer, $candidate);

        foreach ($parts as $part) {
            $doc = $this->resolveFriendlySegment(
                $this->documentSiblingsQuery($parentId, $excludeSystem),
                'title',
                $part,
                $parentPath,
                $path,
                fn (Model $candidate): string => "/docs/by-id/{$candidate->getKey()}",
                allowMarkdownSuffix: true,
                visible: $visible,
            );
            if (! $doc instanceof Document) {
                throw VfsError::notFound($path);
            }

            $parentId = $doc->id;
            $parentPath = rtrim($parentPath, '/').'/'.$part;
        }

        return $doc ?? throw VfsError::notFound($path);
    }

    /**
     * Siblings under one parent, as the naming contract sees them.
     *
     * @return Builder<Document>
     */
    private function documentSiblingsQuery(?string $parentId, bool $excludeSystem): Builder
    {
        $query = Document::forWorkspace()->where('parent_id', $parentId);

        return $excludeSystem ? $query->where('is_system', false) : $query;
    }

    /**
     * Friendly path segment for $doc among its visible siblings.
     */
    private function documentToken(Document $doc, bool $excludeSystem = true): string
    {
        return $this->friendlyToken($this->documentSiblingsQuery($doc->parent_id, $excludeSystem), 'title', $doc);
    }
}
