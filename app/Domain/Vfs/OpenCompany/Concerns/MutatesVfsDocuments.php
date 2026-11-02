<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsTextPatch;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Document content mutation for VFS text patches (/docs and agent documents).
 *
 * Owns the locked compare-and-write for Document rows: the version check, the
 * anchor match, the DocumentVersion snapshot numbering, and the content update
 * all happen inside one transaction holding a row lock on the document, so two
 * concurrent patches serialize instead of overwriting each other. Authorization
 * and mutation policy are the caller's job and must run before this.
 */
trait MutatesVfsDocuments
{
    /**
     * Apply a text patch to a document under a row lock.
     *
     * The patch is re-applied to the locked current content (the caller's
     * preflight may be stale by now). A supplied $version is checked against
     * the locked row with content-anchored staleness (VfsTextPatch).
     *
     * @return array<string, mixed> result envelope; includes stale_base/note when applicable
     *
     * @throws VfsError conflict, ambiguous_match, invalid_request, not_found
     */
    private function patchDocumentContent(User $agent, Document $doc, string $path, VfsTextPatch $patch, ?string $version, string $changeDescription): array
    {
        [$fresh, $applied] = $this->lockedDocumentPatch($agent, $doc, $path, $patch, $version, $changeDescription);

        return [
            'status' => 'updated',
            'path' => $path,
            'canonical_path' => "/docs/by-id/{$fresh->id}",
            'version' => $this->version($fresh->updated_at?->toIso8601String(), $fresh->content ?? ''),
            ...$this->textPatchResultFields($applied),
        ];
    }

    /**
     * @return array{0: Document, 1: array{content: string, replacements: int, stale_base: bool}}
     */
    private function lockedDocumentPatch(User $agent, Document $doc, string $path, VfsTextPatch $patch, ?string $version, string $changeDescription): array
    {
        return DB::transaction(function () use ($agent, $doc, $path, $patch, $version, $changeDescription): array {
            // Invariant: lock, then read, then compare, then write. Snapshot
            // numbering (max + 1) is only race-free while this lock is held.
            $locked = Document::forWorkspace()->whereKey($doc->id)->lockForUpdate()->first()
                ?? throw VfsError::notFound($path);

            $previousContent = (string) ($locked->content ?? '');
            $currentVersion = $this->version($locked->updated_at?->toIso8601String(), $previousContent);
            $applied = $patch->applyAgainstBase($previousContent, $path, $currentVersion, $version);

            if ($previousContent !== $applied['content']) {
                $lastVersion = DocumentVersion::where('document_id', $locked->id)->max('version_number') ?? 0;

                DocumentVersion::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $locked->id,
                    'title' => $locked->title,
                    'content' => $previousContent,
                    'author_id' => $agent->id,
                    'version_number' => $lastVersion + 1,
                    'change_description' => $changeDescription,
                ]);
            }

            $locked->update(['content' => $applied['content']]);

            return [$locked->fresh() ?? $locked, $applied];
        });
    }

    /**
     * Resolve and authorize a document before evaluating approval-gated tool
     * permissions. This ordering prevents requests for documents that could
     * never be mutated from creating unusable approval records.
     *
     * @param  list<string>  $segments
     */
    private function resolvePatchableDocument(User $agent, array $segments, string $path): Document
    {
        $doc = (isset($segments[1], $segments[2]) && $segments[1] === 'by-id')
            ? $this->documentFromByIdSegments($segments, $path)
            : $this->resolveDocumentPath(array_slice($segments, 1), $path);

        if ($doc->is_folder) {
            throw VfsError::notWritable($path);
        }
        if ($doc->is_system) {
            throw VfsError::permissionDenied($path, 'system documents require dedicated agent identity or memory tools');
        }
        if (! $this->canPatchDocument($agent, $doc)) {
            throw VfsError::permissionDenied($path, 'document patch requires explicit folder scope');
        }

        return $doc;
    }
}
