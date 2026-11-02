<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\OpenCompany\Policy\VfsActionClass;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes to an agent's own working area: `notes/…` and `todo.md` under
 * `/agents/by-id/{self}` (usually reached as `/me/notes/…` and `/me/todo.md`).
 *
 * Single writer: only the agent itself creates, overwrites, patches or trashes
 * these documents; managers and reports keep read access through the normal
 * relationship rule. They are system documents inside the agent's document
 * tree, so they never appear under /docs. Folders (notes/ and any subfolder)
 * are created lazily on the first write, never on read.
 *
 * Governance: vfs_write (create/overwrite/restore, reversible_edit). Removal
 * moves the note to the trash (deleted_at plus a trash_batch_id). The agent's
 * own `.trash/` view (`/me/.trash/`, i.e. `/agents/by-id/{self}/.trash/`)
 * lists and reads trashed notes and todo.md, and `mv` from it restores one,
 * like every other mount. Overwrite requires the version from a read, like
 * every other VFS overwrite. Identity and memory files are not written here
 * (they keep their dedicated vfs_patch rules).
 */
trait MutatesVfsAgentArea
{
    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function writeAgentAreaPath(User $agent, array $segments, string $path, string $content, string $mode, ?string $version, array $toolParameters): array
    {
        [$target, $remaining, $root] = $this->agentAreaTarget($agent, $segments, $path);
        $self = "/agents/by-id/{$target->id}";

        $existing = null;
        try {
            $existing = $this->agentDocumentFor($target, $remaining, $path);
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
        }

        if ($existing !== null) {
            if ($existing->is_folder) {
                throw VfsError::notWritable($path);
            }
            if ($mode === 'create') {
                throw VfsError::invalid("Path already exists: {$path}", ['path' => $path, 'hint' => 'Use mode "overwrite" with the version from vfs_read, or vfs_edit.']);
            }
            if ($version === null) {
                throw VfsError::invalid("Overwriting {$path} requires the version you read.", [
                    'path' => $path,
                    'field' => 'version',
                    'hint' => 'Read it first (vfs_read returns version), then pass that version.',
                ]);
            }
            $this->assertVersion($version, $this->documentVersionToken($existing), $path);
            $this->assertMutationToolsAllowed($agent, $path, ['vfs_write'], $toolParameters, $this->channelIdFrom($toolParameters), [
                'kind' => 'agent_note', 'action_class' => 'reversible_edit', 'mode' => 'overwrite', 'title' => $existing->title,
            ], 'write', actionClass: VfsActionClass::REVERSIBLE_EDIT);

            $fresh = DB::transaction(function () use ($agent, $existing, $content, $version, $path): Document {
                $locked = Document::forWorkspace()->whereKey($existing->id)->lockForUpdate()->first() ?? throw VfsError::notFound($path);
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

            return ['status' => 'updated', 'path' => $path, 'canonical_path' => "{$self}/".implode('/', $remaining), 'version' => $this->documentVersionToken($fresh)];
        }

        if ($version !== null) {
            throw VfsError::conflict($path, null, $version, 'It does not exist (any more). Create it without a version.');
        }
        $title = $this->assertValidRecordName((string) end($remaining), $path);

        $this->assertMutationToolsAllowed($agent, $path, ['vfs_write'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'agent_note', 'action_class' => 'reversible_edit', 'mode' => 'create', 'title' => $title,
        ], 'write', actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $doc = DB::transaction(function () use ($agent, $root, $remaining, $title, $content, $path): Document {
            // Lazily create notes/ and any intermediate folders on this write.
            $parent = $root;
            foreach (array_slice($remaining, 0, -1) as $folderTitle) {
                $parent = Document::forWorkspace()->where('parent_id', $parent->id)->where('title', $folderTitle)->first()
                    ?? Document::create([
                        'id' => Str::uuid()->toString(),
                        'workspace_id' => $agent->workspace_id,
                        'title' => $this->assertValidRecordName($folderTitle, $path),
                        'content' => '',
                        'author_id' => $agent->id,
                        'parent_id' => $parent->id,
                        'is_folder' => true,
                        'is_system' => true,
                    ]);
                if (! $parent->is_folder) {
                    throw VfsError::invalid("Path component is a document, not a folder: {$folderTitle}", ['path' => $path]);
                }
            }

            $doc = Document::create([
                'id' => Str::uuid()->toString(),
                'workspace_id' => $agent->workspace_id,
                'title' => $title,
                'content' => $content,
                'content_format' => 'markdown',
                'author_id' => $agent->id,
                'parent_id' => $parent->id,
                'is_folder' => false,
                'is_system' => true,
            ]);
            $fresh = $doc->fresh() ?? $doc;
            $this->snapshotDocumentVersion($fresh, $title, '', $agent, null, $this->documentVersionToken($fresh), 'VFS create');

            return $fresh;
        });

        return ['status' => 'created', 'path' => $path, 'canonical_path' => "{$self}/".implode('/', $remaining), 'version' => $this->documentVersionToken($doc)];
    }

    /**
     * Trash one note (or todo.md). System documents refuse model deletes, so
     * the soft delete is a direct column update; search chunks are not kept
     * for these documents.
     *
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private function trashAgentAreaPath(User $agent, array $segments, string $path): array
    {
        [$target, $remaining] = $this->agentAreaTarget($agent, $segments, $path);
        $doc = $this->agentDocumentFor($target, $remaining, $path);
        if ($doc->is_folder && Document::forWorkspace()->where('parent_id', $doc->id)->exists()) {
            throw VfsError::invalid('Folder is not empty; remove its notes first.', ['path' => $path]);
        }

        Document::withTrashed()->whereKey($doc->id)->toBase()->update([
            'deleted_at' => now(),
            'deleted_by' => $agent->id,
            'trash_batch_id' => (string) Str::uuid(),
        ]);

        return [
            'status' => 'trashed',
            'path' => $path,
            'canonical_path' => "/agents/by-id/{$target->id}/.trash/{$doc->id}",
            'restore_hint' => "mv /me/.trash/{$doc->id} /me/".implode('/', $remaining).' restores it.',
        ];
    }

    /**
     * Trashed notes (and todo.md) of $target's own area, newest first.
     * Readable by whoever may read the agent's documents (self, manager,
     * direct report); restore stays with the agent itself.
     *
     * @return list<VfsEntry>
     */
    private function listAgentAreaTrash(User $viewer, User $target, string $path, VfsBudget $budget): array
    {
        $this->assertCanAccessAgentDocs($viewer, $target, $path);
        $base = "/agents/by-id/{$target->id}/.trash";

        return $this->agentAreaTrashQuery($target)
            ->orderByDesc('deleted_at')
            ->orderBy('id')
            ->limit(max(1, $budget->maxEntries))
            ->get()
            ->map(fn (Document $doc): VfsEntry => new VfsEntry(
                name: $this->slug((string) $doc->title).'--'.$this->idSuffix((string) $doc->id),
                path: rtrim($path, '/').'/'.$doc->id,
                type: 'file',
                canonicalPath: "{$base}/{$doc->id}",
                backendType: 'document',
                backendId: $doc->id,
                capabilities: ['read'],
                metadata: [
                    'title' => $doc->title,
                    'trashed' => true,
                    'deleted_at' => $doc->deleted_at?->toIso8601String(),
                    'deleted_by' => $doc->getAttribute('deleted_by'),
                    'restore_hint' => "mv {$base}/{$doc->id} /agents/by-id/{$target->id}/notes/<name> restores it.",
                ],
            ))
            ->values()
            ->all();
    }

    /**
     * One trashed agent-area document from `.trash/<id or name--id8>`.
     *
     * @throws VfsError not_found
     */
    private function resolveAgentAreaTrashed(User $target, string $token, string $path): Document
    {
        $query = $this->agentAreaTrashQuery($target);
        $doc = preg_match(self::ID_SUFFIX_PATTERN, $token, $m) === 1
            ? $query->where('id', 'like', '%'.$m[2])->first()
            : $query->whereKey($token)->first();

        return $doc ?? throw new VfsError('not_found', "Path not found: {$path}", [
            'path' => $path,
            'hint' => 'ls /me/.trash to see trashed notes.',
        ]);
    }

    /**
     * @param  list<string>  $remaining
     */
    private function agentAreaFolderExists(User $target, array $remaining, string $path): bool
    {
        try {
            return $remaining !== [] && $this->agentDocumentFor($target, $remaining, $path)->is_folder;
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Trashed documents of the agent-writable area only: anything under the
     * notes/ folder tree, plus todo.md at the agent root. Identity and memory
     * files are never part of this view.
     *
     * @return Builder<Document>
     */
    private function agentAreaTrashQuery(User $target): Builder
    {
        $rootId = $target->docs_folder_id;
        $folderIds = [];
        $notes = $rootId ? $this->agentAreaDocument($target, 'notes') : null;
        if ($notes !== null && $notes->is_folder) {
            $frontier = [(string) $notes->id];
            for ($depth = 0; $frontier !== [] && $depth < 20; $depth++) {
                array_push($folderIds, ...$frontier);
                $frontier = Document::forWorkspace()->whereIn('parent_id', $frontier)->where('is_folder', true)->pluck('id')->map(fn ($id): string => (string) $id)->all();
            }
        }

        return Document::onlyTrashed()
            ->where('workspace_id', $target->workspace_id)
            ->where('is_folder', false)
            ->where(function (Builder $query) use ($rootId, $folderIds): void {
                $query->whereIn('parent_id', $folderIds === [] ? ['-'] : $folderIds)
                    ->orWhere(fn (Builder $root) => $root->where('parent_id', $rootId ?? '-')->where('title', 'todo.md'));
            });
    }

    /**
     * Restore a trashed note (or todo.md) of the acting agent to a path in
     * its own area: `mv /me/.trash/<id> /me/notes/<name>` (an existing notes
     * folder as destination keeps the original title). Missing intermediate
     * folders under notes/ are created like on a write. Never overwrites.
     *
     * Governance: same as creating that note (vfs_write, reversible_edit);
     * only the agent itself restores its notes.
     *
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function restoreAgentAreaPath(User $agent, array $segments, string $source, string $destination, array $toolParameters): array
    {
        $target = $this->resolveAgent($agent, array_slice($segments, 1), $source);
        $remaining = $this->agentRemainingSegments(array_slice($segments, 1));
        if (($remaining[0] ?? null) !== '.trash' || ! isset($remaining[1]) || isset($remaining[2])) {
            throw VfsError::unsupported('Only trashed notes can be moved in /agents: mv /me/.trash/<id> /me/notes/<name>.', ['source' => $source]);
        }
        if ($agent->id !== $target->id) {
            throw VfsError::permissionDenied($source, 'only the agent itself restores its notes and todo.md');
        }
        $doc = $this->resolveAgentAreaTrashed($target, $remaining[1], $source);

        // Destination: the agent's own notes/… or todo.md. An existing folder
        // (or notes/ itself) means "into it, keep the title".
        $destinationSegments = $this->segments($destination);
        $destinationTarget = $this->resolveAgent($agent, array_slice($destinationSegments, 1), $destination);
        if ($destinationTarget->id !== $target->id) {
            throw VfsError::unsupported('Restore a note into your own area (/me/notes/…).', ['destination' => $destination]);
        }
        $destinationRemaining = $this->agentRemainingSegments(array_slice($destinationSegments, 1));
        if ($destinationRemaining === ['notes'] || $this->agentAreaFolderExists($target, $destinationRemaining, $destination)) {
            $destinationRemaining[] = (string) $doc->title;
        }
        // Validates "notes/… or todo.md of the acting agent".
        [, $destinationRemaining] = $this->agentAreaTarget($agent, ['agents', 'by-id', (string) $target->id, ...$destinationRemaining], $destination);
        try {
            $this->agentDocumentFor($target, $destinationRemaining, $destination);

            throw VfsError::invalid("Path already exists: {$destination}", ['path' => $destination, 'hint' => 'Restore under a new name.']);
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
        }
        $title = $this->assertValidRecordName((string) end($destinationRemaining), $destination);

        $this->assertMutationToolsAllowed($agent, $source, ['vfs_write'], $toolParameters, $this->channelIdFrom($toolParameters), [
            'kind' => 'agent_note', 'action_class' => 'reversible_edit', 'mode' => 'restore', 'title' => $title,
        ], 'write', actionClass: VfsActionClass::REVERSIBLE_EDIT);

        $root = $this->agentDocumentFolder($target, $source) ?? throw VfsError::notFound($source);
        $fresh = DB::transaction(function () use ($agent, $doc, $root, $destinationRemaining, $title, $destination, $source): Document {
            $parent = $root;
            foreach (array_slice($destinationRemaining, 0, -1) as $folderTitle) {
                $parent = Document::forWorkspace()->where('parent_id', $parent->id)->where('title', $folderTitle)->lockForUpdate()->first()
                    ?? Document::create([
                        'id' => Str::uuid()->toString(),
                        'workspace_id' => $agent->workspace_id,
                        'title' => $this->assertValidRecordName($folderTitle, $destination),
                        'content' => '',
                        'author_id' => $agent->id,
                        'parent_id' => $parent->id,
                        'is_folder' => true,
                        'is_system' => true,
                    ]);
                if (! $parent->is_folder) {
                    throw VfsError::invalid("Path component is a document, not a folder: {$folderTitle}", ['path' => $destination]);
                }
            }
            Document::forWorkspace()->whereKey($parent->id)->lockForUpdate()->first();
            if (Document::forWorkspace()->where('parent_id', $parent->id)->where('title', $title)->exists()) {
                throw VfsError::invalid("Path already exists: {$destination}", ['path' => $destination, 'hint' => 'Restore under a new name.']);
            }

            $locked = Document::onlyTrashed()->where('workspace_id', $agent->workspace_id)->whereKey($doc->id)->lockForUpdate()->first()
                ?? throw VfsError::notFound($source);
            $before = $this->documentVersionToken($locked);
            $previousTitle = (string) $locked->title;
            // Base update: system documents refuse model deletes, and the
            // restore must not depend on the model's trash cascade either.
            Document::withTrashed()->whereKey($locked->id)->toBase()->update([
                'deleted_at' => null,
                'deleted_by' => null,
                'trash_batch_id' => null,
                'parent_id' => $parent->id,
                'title' => $title,
                'updated_at' => now(),
            ]);
            $fresh = Document::forWorkspace()->findOrFail($locked->id);
            $this->snapshotDocumentVersion($fresh, $previousTitle, (string) ($fresh->content ?? ''), $agent, $before, $this->documentVersionToken($fresh), 'VFS restore');

            return $fresh;
        });

        return [
            'status' => 'restored',
            'path' => "/agents/by-id/{$target->id}/".implode('/', $destinationRemaining),
            'canonical_path' => "/agents/by-id/{$target->id}/".implode('/', $destinationRemaining),
            'backend_id' => $fresh->id,
            'version' => $this->documentVersionToken($fresh),
        ];
    }

    /**
     * Resolve and authorize an agent-area path: notes/… or todo.md of the
     * acting agent only.
     *
     * @param  list<string>  $segments
     * @return array{0: User, 1: list<string>, 2: Document}
     */
    private function agentAreaTarget(User $agent, array $segments, string $path): array
    {
        $target = $this->resolveAgent($agent, array_slice($segments, 1), $path);
        $this->assertCanAccessAgentDocs($agent, $target, $path);
        $remaining = $this->agentRemainingSegments(array_slice($segments, 1));

        $isTodo = $remaining === ['todo.md'];
        $isNote = ($remaining[0] ?? null) === 'notes' && count($remaining) >= 2;
        if (! $isTodo && ! $isNote) {
            throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", [
                'path' => $path,
                'hint' => 'Agents write their own notes/… and todo.md (vfs_write). Identity and memory files change through vfs_patch / vfs_edit.',
            ]);
        }
        if ($agent->id !== $target->id) {
            throw VfsError::permissionDenied($path, 'only the agent itself writes its notes and todo.md');
        }

        $root = $this->agentDocumentFolder($target, $path) ?? throw new VfsError('not_writable', "Path is not writable through VFS: {$path}", [
            'path' => $path,
            'hint' => 'This agent has no document area yet.',
        ]);

        return [$target, $remaining, $root];
    }
}
