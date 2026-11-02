<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\Document;
use App\Models\User;
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
 * Governance: vfs_write (create/overwrite, reversible_edit). Removal moves the
 * note to the trash (deleted_at), recoverable by administrators; there is no
 * VFS restore view for agent notes yet. Overwrite requires the version from a
 * read, like every other VFS overwrite. Identity and memory files are not
 * written here (they keep their dedicated vfs_patch rules).
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
            ], 'write');

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
        ], 'write');

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

        Document::withTrashed()->whereKey($doc->id)->toBase()->update(['deleted_at' => now(), 'deleted_by' => $agent->id]);

        return ['status' => 'trashed', 'path' => $path, 'canonical_path' => "/agents/by-id/{$target->id}/".implode('/', $remaining)];
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
