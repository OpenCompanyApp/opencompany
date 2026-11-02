<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\Document;
use App\Models\User;

/**
 * Document mount operations for OpenCompanyVfs.
 *
 * Listing names follow the friendly-name contract (slug, `--{id8}` on sibling
 * collisions); every entry's canonical path is /docs/by-id/{id}. `/docs/.trash/`
 * lists trashed documents the agent can still see (MountsVfsTrash). Writes,
 * deletes, moves and folder creation live in MutatesVfsDocumentRecords.
 */
trait MountsVfsDocuments
{
    /**
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listDocs(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        return $this->pageDocs($agent, $segments, $path, $budget, [])['entries'];
    }

    /**
     * Return an adapter-owned document page before cursor encoding.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $state
     * @return array{
     *     entries: list<VfsEntry>,
     *     has_more: bool,
     *     next_state: array<string, mixed>,
     *     scanned: int,
     *     skipped: int
     * }
     */
    private function pageDocs(User $agent, array $segments, string $path, VfsBudget $budget, array $state): array
    {
        $prefix = null;
        $entryName = null;

        if (($segments[1] ?? null) === '.trash') {
            return $this->paginateVfsStaticEntries($this->listDocumentTrash($agent, $segments, $path, $budget), $budget->maxEntries, $state);
        }

        if (count($segments) === 1) {
            // by-id re-lists every document flat: an alias for recursive
            // walkers (ls -R, find, grep -r), which must not visit each
            // document again through it (VfsEntry::isAlias()).
            $prefix = new VfsEntry('by-id', '/docs/by-id', 'directory', capabilities: ['browse'], metadata: ['alias' => true]);
            $query = Document::forWorkspace()
                ->whereNull('parent_id')
                ->where('is_system', false);
            $entryPath = fn (Document $doc): string => '/docs/'.$this->documentToken($doc);
        } elseif (($segments[1] ?? null) === 'by-id' && ! isset($segments[2])) {
            $query = Document::forWorkspace()->where('is_system', false);
            $entryPath = fn (Document $doc): string => "/docs/by-id/{$doc->id}";
            // by-id lists ids (the names that open under /docs/by-id/), not titles.
            $entryName = fn (Document $doc): string => (string) $doc->id;
        } else {
            $folder = ($segments[1] ?? null) === 'by-id'
                ? $this->documentFromByIdSegments($segments, $path)
                : $this->resolveDocumentPath(array_slice($segments, 1), $path);

            if (! $this->canAccessDocument($agent, $folder)) {
                throw VfsError::permissionDenied($path, 'document folder scope');
            }
            if ($folder->is_system) {
                throw VfsError::permissionDenied($path, 'system document');
            }
            if (! $folder->is_folder) {
                throw VfsError::notReadable($path);
            }

            $query = Document::forWorkspace()
                ->where('parent_id', $folder->id)
                ->where('is_system', false);
            $basePath = ($segments[1] ?? null) === 'by-id'
                ? "/docs/by-id/{$folder->id}"
                : $path;
            $entryPath = fn (Document $doc): string => rtrim($basePath, '/').'/'.$this->documentToken($doc);
        }

        return $this->paginateVfsMountQuery(
            baseQuery: $query,
            labelColumn: 'title',
            limit: $budget->maxEntries,
            scanLimit: $budget->maxFiles,
            state: $state,
            canAccess: fn (Document $doc): bool => $this->canAccessDocument($agent, $doc),
            toEntry: fn (Document $doc): VfsEntry => $this->documentEntry($agent, $doc, $entryPath($doc), $entryName !== null ? $entryName($doc) : null),
            prefixEntry: $prefix,
        );
    }

    /**
     * @param  list<string>  $segments
     */
    private function readDocs(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listDocs($agent, $segments, $path, $budget));
        }
        if ($segments[1] === '.trash') {
            return $this->readDocumentTrash($agent, $segments, $path, $budget);
        }
        if ($segments === ['docs', 'by-id']) {
            if ($budget->isContentRead()) {
                throw VfsError::notReadable($path);
            }

            return $this->entriesToText($this->listDocs($agent, $segments, $path, $budget));
        }

        $doc = (isset($segments[2]) && $segments[1] === 'by-id')
            ? $this->documentFromByIdSegments($segments, $path)
            : $this->resolveDocumentPath(array_slice($segments, 1), $path);

        if (! $this->canAccessDocument($agent, $doc)) {
            throw VfsError::permissionDenied($path, 'document folder scope');
        }
        if ($doc->is_system) {
            throw VfsError::permissionDenied($path, 'system document');
        }

        if ($doc->is_folder) {
            // A content read is for bytes that may be written back; a folder
            // listing must never become file content.
            if ($budget->isContentRead()) {
                throw VfsError::notReadable($path);
            }

            return $this->entriesToText($this->documentChildren($agent, $doc, $path, $budget));
        }

        $content = (string) $doc->content;
        $budget->recordReadVersion($this->version($doc->updated_at?->toIso8601String(), $content));
        $budget->recordCanonicalPath("/docs/by-id/{$doc->id}");

        return VfsText::fit($content, $budget, $path);
    }

    /**
     * @return list<VfsEntry>
     */
    private function documentChildren(User $agent, Document $folder, string $path, VfsBudget $budget): array
    {
        if (! $this->canAccessDocument($agent, $folder)) {
            throw VfsError::permissionDenied($path, 'document folder scope');
        }

        return $this->paginateVfsMountQuery(
            baseQuery: Document::forWorkspace()
                ->where('parent_id', $folder->id)
                ->where('is_system', false),
            labelColumn: 'title',
            limit: $budget->maxEntries,
            scanLimit: $budget->maxFiles,
            state: [],
            canAccess: fn (Document $doc): bool => $this->canAccessDocument($agent, $doc),
            toEntry: fn (Document $doc): VfsEntry => $this->documentEntry(
                $agent,
                $doc,
                rtrim($path, '/').'/'.$this->documentToken($doc),
            ),
        )['entries'];
    }

    /**
     * @param  string|null  $name  listing name; defaults to the title (which
     *                             resolves among its siblings)
     */
    private function documentEntry(User $agent, Document $doc, string $path, ?string $name = null): VfsEntry
    {
        $capabilities = $doc->is_folder ? ['browse', 'read', 'search'] : ['read', 'search'];
        $metadata = [
            'title' => $doc->title,
            'is_system' => $doc->is_system,
            'content_format' => $doc->content_format ?? 'markdown',
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];

        if (! $doc->is_folder) {
            $patchDecision = $this->entryMutationDecision(
                $agent,
                $path,
                'patch',
                ['vfs_patch'],
                'Patch this document with vfs_patch using a targeted search/replace payload.',
                $this->canPatchDocument($agent, $doc),
                $this->documentWriteDenial($agent, $doc, null),
            );
            $capabilities = $this->mutationCapabilities($capabilities, ['patch' => $patchDecision]);
            $metadata = [
                ...$metadata,
                ...$patchDecision->metadata(),
                ...$this->mutationActionsMetadata(['patch' => $patchDecision]),
            ];
        }

        return new VfsEntry(
            name: $name ?? $doc->title,
            path: $path,
            type: $doc->is_folder ? 'directory' : 'file',
            canonicalPath: "/docs/by-id/{$doc->id}",
            backendType: 'document',
            backendId: $doc->id,
            version: $this->version($doc->updated_at?->toIso8601String(), $doc->content ?? ''),
            capabilities: $capabilities,
            metadata: $metadata,
        );
    }
}
