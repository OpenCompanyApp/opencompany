<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Document lifecycle through the VFS: create, overwrite, trash, move/rename,
 * folder creation and restore from `/docs/.trash/`.
 *
 * Path forms accepted for a target: `/docs/<folder path>/<Title>` (with or
 * without `.md`), `/docs/by-id/<folderId>/<Title>`, `/docs/by-id/<docId>`.
 *
 * Governance (never less governed than the equivalent domain tool):
 * - every write/create/delete/move authorization goes through ONE gate,
 *   assertDocumentWritable(). Stream C2 is reworking the document permission
 *   model; integration swaps that helper's internals and nothing else.
 * - tool permissions compose the VFS primitive with the domain tool:
 *   create = vfs_write + create_document, overwrite = vfs_write +
 *   update_document. rm/mv/mkdir/restore compose the domain tool
 *   (delete_document, update_document, create_document); the vfs_write
 *   primitive for those is enforced by the calling surface (shell exec
 *   composition, script-tool dispatch mapping vfs_rm/vfs_mv/vfs_mkdir to
 *   vfs_write), exactly as for /files today. Approval rows name the
 *   replayable direct tool (vfs_write / vfs_rm / vfs_mv / vfs_mkdir).
 * - action classes: create/overwrite/move = reversible_edit, rm = delete
 *   (moves to trash, restorable), never a hard delete. Purge is not a VFS
 *   operation.
 * - overwrite REQUIRES the version from a read (human edits win through
 *   version checks: a stale version is `conflict`).
 *
 * Every committed change records a DocumentVersion snapshot attributed to
 * the agent and run (RecordsVfsHistory).
 */
trait MutatesVfsDocumentRecords
{
    /**
     * The single authorization gate for document mutations.
     *
     * $doc set: mutate that existing document (overwrite, trash, move away).
     * $doc null: create inside $folder (null = workspace root).
     *
     * Today's rule, unchanged: system documents are never writable here, and
     * writes need an explicit document-folder grant that contains the target
     * (an existing document must sit strictly inside a granted folder, like
     * the delete/update domain tools; a new document must go into a granted
     * folder or below). Default (no grants) agents read everything and write
     * nothing.
     *
     * @throws VfsError permission_denied
     */
    private function assertDocumentWritable(User $agent, ?Document $folder, ?Document $doc, string $path): void
    {
        // Single write gate for every document create/overwrite/patch/move/
        // trash. The decision itself is owned by AuthorizesVfsDocuments
        // (folder grants, deny rows, stored DocumentPermission rows) so the
        // VFS has exactly one document write policy; do not re-implement
        // grant checks here.
        if ($doc === null && $folder !== null && ($folder->is_system || ! $folder->is_folder)) {
            throw VfsError::permissionDenied($path, 'documents can only be created inside non-system document folders');
        }

        $denial = $this->documentWriteDenial($agent, $doc, $doc === null ? $folder?->id : null);
        if ($denial !== null) {
            throw VfsError::permissionDenied($path, $denial);
        }
    }

    /**
     * Create or overwrite a document from vfs_write.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function writeDocumentPath(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array
    {
        if (($segments[1] ?? null) === '.trash') {
            throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", [
                'path' => $path,
                'hint' => 'Trash is read-only. Restore with mv '.$path.' <destination>.',
            ]);
        }

        $target = $this->locateDocumentWriteTarget($segments, $path);
        $doc = $target['doc'];

        if ($doc !== null) {
            if ($doc->is_folder) {
                throw VfsError::notWritable($path);
            }
            if ($mode === 'create') {
                throw VfsError::invalid("Path already exists: {$path}", [
                    'path' => $path,
                    'canonical_path' => "/docs/by-id/{$doc->id}",
                    'hint' => 'Use mode "overwrite" with the version from vfs_read, or vfs_edit for targeted changes.',
                ]);
            }

            return $this->overwriteDocumentRecord($agent, $doc, $path, $content, $version, $toolParameters);
        }

        if ($version !== null) {
            throw VfsError::conflict($path, null, $version, 'The document does not exist (any more). Create it without a version.');
        }

        return $this->createDocumentRecord($agent, $target['folder'], (string) $target['title'], $path, $content, false, $toolParameters, 'vfs_write');
    }

    /**
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function overwriteDocumentRecord(User $agent, Document $doc, string $path, string $content, ?string $version, array $toolParameters): array
    {
        $this->assertDocumentWritable($agent, null, $doc, $path);

        $current = $this->version($doc->updated_at?->toIso8601String(), (string) ($doc->content ?? ''));
        if ($version === null) {
            throw VfsError::invalid("Overwriting {$path} requires the version you read.", [
                'path' => $path,
                'field' => 'version',
                'hint' => 'Read it first (vfs_read returns version), then pass that version. For targeted changes use vfs_edit or vfs_patch.',
            ]);
        }
        // Unlocked precheck so a stale overwrite never creates an approval.
        $this->assertVersion($version, $current, $path);

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_write', 'update_document'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'document',
            'action_class' => 'reversible_edit',
            'mode' => 'overwrite',
            'title' => $doc->title,
            'bytes' => strlen($content),
        ], 'write', 'Overwriting a document requires vfs_write and update_document.', actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $fresh = DB::transaction(function () use ($agent, $doc, $path, $content, $version): Document {
            // Invariant: lock, then read, then compare, then write + snapshot.
            $locked = Document::forWorkspace()->whereKey($doc->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $previous = (string) ($locked->content ?? '');
            $before = $this->version($locked->updated_at?->toIso8601String(), $previous);
            $this->assertVersion($version, $before, $path);
            if ($previous === $content) {
                return $locked;
            }

            $locked->update(['content' => $content]);
            $fresh = $locked->fresh() ?? $locked;
            $this->snapshotDocumentVersion($fresh, (string) $locked->title, $previous, $agent, $before, $this->documentVersionToken($fresh), 'VFS overwrite');

            return $fresh;
        });

        return $this->documentWriteResult('updated', $fresh);
    }

    /**
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function createDocumentRecord(User $agent, ?Document $folder, string $title, string $path, string $content, bool $isFolder, array $toolParameters, string $directTool): array
    {
        $title = $this->assertValidRecordName($title, $path);
        $this->assertDocumentWritable($agent, $folder, null, $path);

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: $directTool,
            operation: $isFolder ? 'mkdir' : 'write',
            subject: $path,
            requiredTools: $directTool === 'vfs_write' ? ['vfs_write', 'create_document'] : ['create_document'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Creating documents requires create_document.',
            preview: [
                'kind' => $isFolder ? 'document_folder' : 'document',
                'action_class' => 'reversible_edit',
                'mode' => 'create',
                'title' => $title,
                'folder' => $folder?->title,
                'bytes' => strlen($content),
            ],
            actionClass: VfsActionClass::REVERSIBLE_EDIT,
        );

        $doc = DB::transaction(function () use ($agent, $folder, $title, $content, $isFolder, $path): Document {
            // Re-check the sibling under the parent lock: a concurrent create
            // of the same title must not produce two "same name" documents
            // from one VFS path.
            if ($folder !== null) {
                Document::forWorkspace()->whereKey($folder->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            }
            $exists = Document::forWorkspace()
                ->where('parent_id', $folder?->id)
                ->where('is_system', false)
                ->where('title', $title)
                ->exists();
            if ($exists) {
                throw VfsError::invalid("Path already exists: {$path}", ['path' => $path]);
            }

            $doc = Document::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $agent->workspace_id,
                'title' => $title,
                'content' => $isFolder ? '' : $content,
                'content_format' => 'markdown',
                'author_id' => $agent->id,
                'parent_id' => $folder?->id,
                'is_folder' => $isFolder,
            ]);
            $fresh = $doc->fresh() ?? $doc;
            if (! $isFolder) {
                // Creation is part of the run's change set: v1 is "did not exist".
                $this->snapshotDocumentVersion($fresh, $title, '', $agent, null, $this->documentVersionToken($fresh), 'VFS create');
            }

            return $fresh;
        });

        return $this->documentWriteResult('created', $doc);
    }

    /**
     * Resolve where a write lands: an existing document, or a parent folder
     * plus the new title.
     *
     * @param  list<string>  $segments
     * @return array{doc: Document|null, folder: Document|null, title: string|null}
     */
    private function locateDocumentWriteTarget(array $segments, string $path): array
    {
        $parts = array_slice($segments, 1);
        if ($parts === [] || ($parts[0] === 'by-id' && ! isset($parts[1]))) {
            throw VfsError::notWritable($path);
        }

        try {
            $doc = $parts[0] === 'by-id'
                ? $this->documentFromByIdSegments($segments, $path)
                : $this->resolveDocumentPath($parts, $path);
            if ($doc->is_system) {
                throw VfsError::permissionDenied($path, 'system documents are edited through /agents or /me, not /docs');
            }

            return ['doc' => $doc, 'folder' => null, 'title' => null];
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
        }

        if ($parts[0] === 'by-id' && count($parts) === 2) {
            // /docs/by-id/<id> must name an existing document.
            throw VfsError::notFound($path);
        }

        $title = (string) array_pop($parts);
        if (preg_match('/\.md$/i', $title) === 1 && strlen($title) > 3) {
            $title = substr($title, 0, -3);
        }

        if ($parts === []) {
            $folder = null;
        } elseif ($parts[0] === 'by-id' && count($parts) === 2) {
            $folder = $this->findDocument($parts[1], $path);
        } else {
            $folder = $parts[0] === 'by-id'
                ? $this->documentFromByIdSegments(['docs', ...$parts], $path)
                : $this->resolveDocumentPath($parts, $path);
        }
        if ($folder !== null && ! $folder->is_folder) {
            throw VfsError::invalid("Parent is a document, not a folder: {$path}", ['path' => $path]);
        }

        return ['doc' => null, 'folder' => $folder, 'title' => $title];
    }

    /**
     * Names that would break the naming contract are refused: empty, hidden
     * (`.trash`, `.history` are reserved views) or containing a path
     * separator. (A `--x` tail is harmless: slugs collapse `--`.)
     */
    private function assertValidRecordName(string $name, string $path): string
    {
        $name = trim($name);
        if ($name === '' || str_starts_with($name, '.') || str_contains($name, '/')) {
            throw VfsError::invalid("Invalid name for {$path}.", [
                'path' => $path,
                'hint' => 'Names must be non-empty and must not start with "." (reserved for .trash and .history views).',
            ]);
        }

        return $name;
    }

    /**
     * Move a live document to the trash (soft delete). Folders take their
     * whole subtree with them, stamped with one deleted_at so restore brings
     * back exactly that batch.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function trashDocumentPath(User $agent, array $segments, string $path, array $toolParameters): array
    {
        $doc = (($segments[1] ?? null) === 'by-id')
            ? $this->documentFromByIdSegments($segments, $path)
            : $this->resolveDocumentPath(array_slice($segments, 1), $path);
        $this->assertDocumentWritable($agent, null, $doc, $path);

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_rm',
            operation: 'delete',
            subject: $path,
            requiredTools: ['delete_document'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: 'Moving a document to the trash requires delete_document.',
            preview: ['kind' => $doc->is_folder ? 'document_folder' : 'document', 'action_class' => 'delete', 'title' => $doc->title, 'id' => $doc->id],
            actionClass: VfsActionClass::DELETE,
        );

        $count = DB::transaction(function () use ($agent, $doc, $path): int {
            $locked = Document::forWorkspace()->whereKey($doc->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
            $ids = [$locked->id, ...$this->liveDocumentDescendantIds($locked)];
            $stamp = now();

            foreach (Document::forWorkspace()->whereKey($ids)->get() as $member) {
                // Model delete so observers drop search chunks; SoftDeletes
                // turns it into a trash move.
                $member->delete();
            }
            Document::withTrashed()->whereKey($ids)->toBase()->update(['deleted_at' => $stamp, 'deleted_by' => $agent->id]);

            return count($ids);
        });

        return [
            'status' => 'trashed',
            'path' => $path,
            'canonical_path' => "/docs/.trash/{$doc->id}",
            'trashed' => $count,
            'restore_hint' => "mv /docs/.trash/{$doc->id} <destination folder or path> restores it.",
        ];
    }

    /**
     * @return list<string>
     */
    private function liveDocumentDescendantIds(Document $folder): array
    {
        if (! $folder->is_folder) {
            return [];
        }

        $ids = [];
        $frontier = [$folder->id];
        for ($depth = 0; $frontier !== [] && $depth < 30; $depth++) {
            $children = Document::forWorkspace()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id): string => (string) $id)->all();
            array_push($ids, ...$children);
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * Rename and/or move a document or folder inside /docs, or restore one
     * from /docs/.trash/ to the destination.
     *
     * Destination rules: an existing folder means "into that folder, keep the
     * title"; otherwise the destination's parent must be a folder (or /docs)
     * and its last segment becomes the title. Existing documents are never
     * overwritten by mv.
     *
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function moveDocumentPath(User $agent, string $source, string $destination, array $toolParameters): array
    {
        $sourceSegments = $this->segments($source);
        $destinationSegments = $this->segments($destination);
        if (($destinationSegments[0] ?? null) !== 'docs') {
            throw VfsError::unsupported('Documents can only be moved within /docs.', ['source' => $source, 'destination' => $destination]);
        }
        if (($destinationSegments[1] ?? null) === '.trash') {
            throw VfsError::invalid('Use rm to move a document to the trash.', ['destination' => $destination]);
        }

        $fromTrash = ($sourceSegments[1] ?? null) === '.trash';
        $doc = $fromTrash
            ? $this->resolveTrashedDocument($agent, $sourceSegments, $source)
            : ((($sourceSegments[1] ?? null) === 'by-id')
                ? $this->documentFromByIdSegments($sourceSegments, $source)
                : $this->resolveDocumentPath(array_slice($sourceSegments, 1), $source));

        [$folder, $title] = $this->documentMoveDestination($doc, $destinationSegments, $destination);
        $title = $this->assertValidRecordName($title, $destination);

        if (! $fromTrash && $folder?->id === $doc->parent_id && $title === $doc->title) {
            return [...$this->documentWriteResult('unchanged', $doc), 'path' => $this->documentFriendlyPath($doc)];
        }

        // Both sides are authorized: leaving the source and entering the target.
        $this->assertDocumentWritable($agent, null, $doc, $source);
        $this->assertDocumentWritable($agent, $folder, null, $destination);
        if ($doc->is_folder && $folder !== null && $this->documentIsSelfOrAncestorOf($doc, $folder)) {
            throw VfsError::invalid('Cannot move a folder into itself.', ['source' => $source, 'destination' => $destination]);
        }

        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_mv',
            operation: $fromTrash ? 'restore' : 'move',
            subject: $source,
            requiredTools: [$fromTrash ? 'delete_document' : 'update_document'],
            parameters: $toolParameters,
            channelId: $this->channelIdFrom($toolParameters),
            hint: $fromTrash ? 'Restoring a trashed document requires delete_document.' : 'Moving or renaming a document requires update_document.',
            preview: ['kind' => 'document', 'action_class' => 'reversible_edit', 'title' => $doc->title, 'to_title' => $title, 'to_folder' => $folder?->title],
            actionClass: VfsActionClass::REVERSIBLE_EDIT,
        );

        $fresh = DB::transaction(function () use ($agent, $doc, $folder, $title, $fromTrash, $source): Document {
            $query = $fromTrash ? Document::withTrashed()->where('workspace_id', $agent->workspace_id) : Document::forWorkspace();
            $locked = $query->whereKey($doc->id)->lockForUpdate()->first() ?? throw VfsError::notFound($source);

            $clash = Document::forWorkspace()
                ->where('parent_id', $folder?->id)
                ->where('is_system', false)
                ->where('title', $title)
                ->whereKeyNot($locked->id)
                ->exists();
            if ($clash) {
                throw VfsError::invalid("Destination already exists: {$title}", ['title' => $title]);
            }

            if ($fromTrash) {
                $this->restoreDocumentBatch($locked);
            }

            $before = $this->documentVersionToken($locked);
            $previousTitle = (string) $locked->title;
            $locked->forceFill(['parent_id' => $folder?->id, 'title' => $title])->save();
            $fresh = $locked->fresh() ?? $locked;
            if (! $fresh->is_folder) {
                $this->snapshotDocumentVersion($fresh, $previousTitle, (string) ($fresh->content ?? ''), $agent, $before, $this->documentVersionToken($fresh), $fromTrash ? 'VFS restore' : 'VFS move');
            }

            return $fresh;
        });

        return $this->documentWriteResult($fromTrash ? 'restored' : 'moved', $fresh);
    }

    /**
     * @param  list<string>  $destinationSegments
     * @return array{0: Document|null, 1: string}
     */
    private function documentMoveDestination(Document $doc, array $destinationSegments, string $destination): array
    {
        if (count($destinationSegments) === 1) {
            return [null, (string) $doc->title];
        }

        try {
            $existing = ($destinationSegments[1] === 'by-id')
                ? $this->documentFromByIdSegments($destinationSegments, $destination)
                : $this->resolveDocumentPath(array_slice($destinationSegments, 1), $destination);
            if ($existing->id === $doc->id) {
                return [$doc->parent_id ? Document::forWorkspace()->find($doc->parent_id) : null, (string) $doc->title];
            }
            if (! $existing->is_folder) {
                throw VfsError::invalid("Destination already exists: {$destination}", [
                    'destination' => $destination,
                    'hint' => 'mv never overwrites a document. Move it to a new name, or rm the destination first.',
                ]);
            }

            return [$existing, (string) $doc->title];
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
        }

        $target = $this->locateDocumentWriteTarget($destinationSegments, $destination);

        return [$target['folder'], (string) $target['title']];
    }

    private function documentIsSelfOrAncestorOf(Document $candidateAncestor, Document $folder): bool
    {
        // documentChain() is the folder id followed by its ancestors.
        return in_array((string) $candidateAncestor->id, $this->documentChain($folder), true);
    }

    /**
     * Create a folder (and any missing intermediate folders, like mkdir -p).
     *
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function makeDocumentFolderPath(User $agent, string $path, array $toolParameters): array
    {
        $segments = $this->segments($path);
        if (count($segments) < 2 || in_array($segments[1], ['.trash', 'by-id'], true) && count($segments) < 4) {
            throw VfsError::invalid('mkdir requires a folder path such as /docs/Reports/2026 or /docs/by-id/<folderId>/<Name>.', ['path' => $path]);
        }

        try {
            $existing = $segments[1] === 'by-id'
                ? $this->documentFromByIdSegments($segments, $path)
                : $this->resolveDocumentPath(array_slice($segments, 1), $path);
            if (! $existing->is_folder) {
                throw VfsError::invalid("Path already exists and is not a directory: {$path}", ['path' => $path]);
            }

            return ['status' => 'exists', ...$this->documentWriteResult('exists', $existing)];
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
        }

        // Walk from the deepest existing ancestor and create the rest.
        $parts = $segments[1] === 'by-id' ? array_slice($segments, 3) : array_slice($segments, 1);
        $folder = $segments[1] === 'by-id' ? $this->findDocument($segments[2], $path) : null;
        $result = null;
        foreach ($parts as $part) {
            $basePath = $folder !== null ? "/docs/by-id/{$folder->id}" : '/docs';
            try {
                $folder = $this->resolveDocumentPathFromParent($folder?->id, [$part], $path, $basePath, true);
                if (! $folder->is_folder) {
                    throw VfsError::invalid("Path component is a document, not a folder: {$part}", ['path' => $path]);
                }

                continue;
            } catch (VfsError $e) {
                if ($e->errorCode !== 'not_found') {
                    throw $e;
                }
            }

            $result = $this->createDocumentRecord($agent, $folder, $part, $path, '', true, $toolParameters, 'vfs_mkdir');
            $folder = Document::forWorkspace()->findOrFail($result['backend_id']);
        }

        return $result ?? throw VfsError::notFound($path);
    }

    private function documentVersionToken(Document $doc): string
    {
        return $this->version($doc->updated_at?->toIso8601String(), (string) ($doc->content ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentWriteResult(string $status, Document $doc): array
    {
        return [
            'status' => $status,
            'path' => $this->documentFriendlyPath($doc),
            'canonical_path' => "/docs/by-id/{$doc->id}",
            'backend_id' => $doc->id,
            'type' => $doc->is_folder ? 'directory' : 'file',
            ...($doc->is_folder ? [] : ['version' => $this->documentVersionToken($doc)]),
        ];
    }

    /**
     * Friendly /docs path of a live document (canonical path when any
     * ancestor is a system folder and therefore has no /docs name).
     */
    private function documentFriendlyPath(Document $doc): string
    {
        // Called after mutations: names computed before the write are stale.
        $this->friendlyTokenCache = [];
        $tokens = [];
        $current = $doc;
        for ($depth = 0; $current !== null && $depth < 30; $depth++) {
            if ($current->is_system) {
                return "/docs/by-id/{$doc->id}";
            }
            array_unshift($tokens, $this->documentToken($current));
            $current = $current->parent_id ? Document::forWorkspace()->find($current->parent_id) : null;
        }

        return '/docs/'.implode('/', $tokens);
    }

    /**
     * Trashed documents the agent could still read, top-level batch members
     * only (a trashed folder lists once; its subtree comes back with it).
     *
     * @param  list<string>  $segments
     * @return list<VfsEntry>
     */
    private function listDocumentTrash(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (isset($segments[2])) {
            $doc = $this->resolveTrashedDocument($agent, $segments, $path);
            if (! $doc->is_folder) {
                throw VfsError::notReadable($path);
            }

            return Document::onlyTrashed()
                ->where('workspace_id', $agent->workspace_id)
                ->where('parent_id', $doc->id)
                ->orderBy('title')
                ->limit($budget->maxEntries)
                ->get()
                ->map(fn (Document $child): VfsEntry => $this->trashedDocumentEntry($child, rtrim($path, '/').'/'.$child->id))
                ->all();
        }

        return Document::onlyTrashed()
            ->where('workspace_id', $agent->workspace_id)
            ->where('is_system', false)
            ->orderByDesc('deleted_at')
            ->orderBy('id')
            ->limit(max(1, $budget->maxFiles))
            ->get()
            ->filter(fn (Document $doc): bool => $this->isTrashBatchRoot($doc) && $this->canAccessTrashedDocument($agent, $doc))
            ->take($budget->maxEntries)
            ->map(fn (Document $doc): VfsEntry => $this->trashedDocumentEntry($doc, "/docs/.trash/{$doc->id}"))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $segments
     */
    private function readDocumentTrash(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (! isset($segments[2])) {
            return $this->entriesToText($this->listDocumentTrash($agent, $segments, $path, $budget));
        }

        $doc = $this->resolveTrashedDocument($agent, $segments, $path);
        if ($doc->is_folder) {
            if ($budget->isContentRead()) {
                throw VfsError::notReadable($path);
            }

            return $this->entriesToText($this->listDocumentTrash($agent, $segments, $path, $budget));
        }

        $budget->recordCanonicalPath("/docs/.trash/{$doc->id}");

        return VfsText::fit((string) $doc->content, $budget, $path);
    }

    /**
     * Resolve `/docs/.trash/<id or name--id8>[/<child id>...]`.
     *
     * @param  list<string>  $segments
     */
    private function resolveTrashedDocument(User $agent, array $segments, string $path): Document
    {
        $doc = null;
        foreach (array_slice($segments, 2) as $token) {
            $id = preg_match(self::ID_SUFFIX_PATTERN, $token, $m) === 1 ? null : $token;
            $query = Document::onlyTrashed()->where('workspace_id', $agent->workspace_id)->where('is_system', false);
            $query = $doc !== null ? $query->where('parent_id', $doc->id) : $query;
            $doc = $id !== null
                ? $query->whereKey($id)->first()
                : $query->where('id', 'like', '%'.$m[2])->first();
            if ($doc === null) {
                throw new VfsError('not_found', "Path not found: {$path}", ['path' => $path, 'hint' => 'ls /docs/.trash to see trashed documents.']);
            }
        }
        if ($doc === null || ! $this->canAccessTrashedDocument($agent, $doc)) {
            throw VfsError::notFound($path);
        }

        return $doc;
    }

    private function trashedDocumentEntry(Document $doc, string $path): VfsEntry
    {
        return new VfsEntry(
            name: $this->slug((string) $doc->title).'--'.$this->idSuffix($doc->id),
            path: $path,
            type: $doc->is_folder ? 'directory' : 'file',
            canonicalPath: "/docs/.trash/{$doc->id}",
            backendType: 'document',
            backendId: $doc->id,
            capabilities: $doc->is_folder ? ['browse', 'read'] : ['read'],
            metadata: [
                'title' => $doc->title,
                'trashed' => true,
                'deleted_at' => $doc->deleted_at?->toIso8601String(),
                'deleted_by' => $doc->getAttribute('deleted_by'),
                'original_parent' => $doc->parent_id ? "/docs/by-id/{$doc->parent_id}" : '/docs',
                'restore_hint' => "mv /docs/.trash/{$doc->id} <destination> restores it.",
            ],
        );
    }

    private function isTrashBatchRoot(Document $doc): bool
    {
        if (! $doc->parent_id) {
            return true;
        }

        $parent = Document::withTrashed()->find($doc->parent_id);

        return $parent === null
            || $parent->deleted_at === null
            || $parent->deleted_at->ne($doc->deleted_at);
    }

    /**
     * Read access for a trashed document: the same folder-grant rule as live
     * documents, walking the parent chain including trashed ancestors.
     */
    private function canAccessTrashedDocument(User $agent, Document $doc): bool
    {
        if ($doc->is_system) {
            return false;
        }

        $allowed = $this->permissions->getAllowedFolderIds($agent);
        if ($allowed === null) {
            return true;
        }

        $parentId = $doc->parent_id;
        for ($depth = 0; $parentId && $depth < 30; $depth++) {
            if (in_array($parentId, $allowed, true)) {
                return true;
            }
            $parentId = Document::withTrashed()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }

    /**
     * Restore a trashed document and every descendant trashed in the same
     * batch (same deleted_at). Call inside the move transaction.
     */
    private function restoreDocumentBatch(Document $doc): void
    {
        $stamp = $doc->deleted_at;
        $ids = [$doc->id];
        $frontier = [$doc->id];
        for ($depth = 0; $frontier !== [] && $depth < 30; $depth++) {
            $children = Document::onlyTrashed()
                ->whereIn('parent_id', $frontier)
                ->where('deleted_at', $stamp)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();
            array_push($ids, ...$children);
            $frontier = $children;
        }

        foreach (Document::onlyTrashed()->whereKey($ids)->get() as $member) {
            $member->forceFill(['deleted_by' => null]);
            $member->restore();
        }
        $doc->refresh();
    }
}
