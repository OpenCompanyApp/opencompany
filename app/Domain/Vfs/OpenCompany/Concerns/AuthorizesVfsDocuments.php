<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\User;

/**
 * Central document authorization rules for all document-backed VFS mounts.
 *
 * Documents surface through /docs directly and through agent memory/files
 * aliases. Keeping the permission contract here prevents those mounts from
 * drifting on folder-scope semantics or accidentally making system documents
 * writable.
 *
 * Governance (docs/architecture/governance-model.md "Permissions and
 * effective access"): access will become Space-level only. Until stored rows
 * are migrated to Spaces, the VFS enforces exactly the rows that exist today
 * and adds no new per-object grant kinds:
 *
 * Read, in this order (an earlier denial is never widened later):
 *   1. explicit folder deny rows (AgentPermission scope `folder`, `deny`) on
 *      the document or any ancestor deny it;
 *   2. folder allow rows: when the agent has any, the document must be an
 *      allowed folder or inside one (no rows = every non-system document);
 *   3. DocumentPermission rows (per-document viewer/editor sharing): every
 *      document in the chain (itself and its ancestors) that has rows is
 *      restricted to the listed principals and its author.
 * Write additionally requires: not a system document, an explicit folder
 * allow row covering the target (today's rule: unrestricted agents read all
 * and write none), and the `editor` role (or authorship) on every restricted
 * document in the chain.
 *
 * Stream C1 calls canWriteDocument() from its single document write helper
 * and documentWriteDenial() for capability metadata and errors.
 */
trait AuthorizesVfsDocuments
{
    private function canAccessDocument(User $agent, Document $doc): bool
    {
        return $this->documentReadDenial($agent, $doc) === null;
    }

    /** Content patch of an existing, non-folder document. */
    private function canPatchDocument(User $agent, Document $doc): bool
    {
        return ! $doc->is_folder && $this->canWriteDocument($agent, $doc, null);
    }

    /**
     * Whether $agent may create, overwrite, patch, move, rename or trash a
     * document.
     *
     * Pass the existing document for edits of it ($folderId ignored), or
     * null plus the target folder id for creates (null folder = /docs root,
     * which no folder grant covers). A folder document is a valid target
     * (rename/move/trash of a folder); content patch callers must still
     * reject folders themselves (see canPatchDocument).
     */
    public function canWriteDocument(User $agent, ?Document $doc, ?string $folderId): bool
    {
        return $this->documentWriteDenial($agent, $doc, $folderId) === null;
    }

    /**
     * The exact reason $agent may not write the target, phrased so it names
     * the grant that would enable the write; null when the write is allowed.
     */
    public function documentWriteDenial(User $agent, ?Document $doc, ?string $folderId): ?string
    {
        if ($doc?->is_system) {
            return 'system documents (agent identity and memory) are written through /agents/{agent}/identity or /memory, not /docs';
        }

        $folder = $doc === null && $folderId !== null
            ? Document::forWorkspace()->where('is_folder', true)->find($folderId)
            : null;
        if ($doc === null && $folderId !== null && $folder === null) {
            return "document folder {$folderId} does not exist in this workspace";
        }

        $target = $doc ?? $folder;
        if ($target !== null && ($readDenial = $this->documentReadDenial($agent, $target)) !== null) {
            return $readDenial;
        }

        $allowedFolderIds = $this->permissions->getAllowedFolderIds($agent);
        if ($allowedFolderIds === null) {
            return 'writing documents requires an explicit document-folder grant; ask a workspace admin to allow the folder that contains this document for this agent (agent Capabilities → Documents)';
        }

        $chain = $target === null ? [] : $this->documentChain($target);
        $folderChain = $doc !== null && ! $doc->is_folder ? array_slice($chain, 1) : $chain;
        if (array_intersect($folderChain, $allowedFolderIds) === []) {
            $where = $target === null ? 'the /docs root' : '"'.$target->title.'"';

            return "this agent's document-folder grant does not cover {$where}; ask a workspace admin to allow its folder (or a parent folder) for this agent (agent Capabilities → Documents)";
        }

        $restricted = $this->restrictedDocumentNode($agent, $chain, 'editor');
        if ($restricted !== null) {
            return "\"{$restricted['title']}\" is shared with specific people; this agent needs the editor role on it (document sharing settings)";
        }

        return null;
    }

    /**
     * Null when readable, else a reason naming the blocking rule.
     */
    private function documentReadDenial(User $agent, Document $doc): ?string
    {
        $chain = $this->documentChain($doc);

        // Invariant: explicit deny is evaluated before any allow and cannot
        // be widened by a folder grant or a sharing row.
        $denied = array_intersect($chain, $this->permissions->getDeniedFolderIds($agent));
        if ($denied !== []) {
            return 'a workspace admin denied this agent access to the folder containing this document';
        }

        $allowedFolderIds = $this->permissions->getAllowedFolderIds($agent);
        if ($allowedFolderIds !== null) {
            // A non-folder document is covered only through its ancestors;
            // a folder is covered by its own grant too.
            $coveredBy = $doc->is_folder ? $chain : array_slice($chain, 1);
            if (array_intersect($coveredBy, $allowedFolderIds) === []) {
                return "this agent's document-folder grant does not include this document; ask a workspace admin to allow its folder for this agent";
            }
        }

        $restricted = $this->restrictedDocumentNode($agent, $chain, null);
        if ($restricted !== null) {
            return "\"{$restricted['title']}\" is shared with specific people and this agent is not one of them";
        }

        return null;
    }

    /**
     * First document in $chain whose DocumentPermission rows exclude $agent
     * (for $role `editor`: rows exist and the agent is neither an editor nor
     * the author). Null when no restricting row applies.
     *
     * @param  list<string>  $chain
     * @return array{id: string, title: string}|null
     */
    private function restrictedDocumentNode(User $agent, array $chain, ?string $role): ?array
    {
        if ($chain === []) {
            return null;
        }

        $rows = DocumentPermission::query()
            ->whereIn('document_id', $chain)
            ->get(['document_id', 'user_id', 'role'])
            ->groupBy('document_id');
        if ($rows->isEmpty()) {
            return null;
        }

        $nodes = Document::query()->whereIn('id', $rows->keys()->all())->get(['id', 'title', 'author_id'])->keyBy('id');
        foreach ($chain as $documentId) {
            $nodeRows = $rows->get($documentId);
            if ($nodeRows === null || $nodeRows->isEmpty()) {
                continue;
            }

            $node = $nodes->get($documentId);
            if ($node !== null && $node->author_id === $agent->id) {
                continue;
            }

            $mine = $nodeRows->firstWhere('user_id', $agent->id);
            if ($mine === null || ($role === 'editor' && $mine->role !== 'editor')) {
                return ['id' => $documentId, 'title' => (string) ($node->title ?? $documentId)];
            }
        }

        return null;
    }

    /**
     * The document id followed by its ancestor ids, nearest first (bounded
     * depth; the parent walk stays inside the document's own tree).
     *
     * @return list<string>
     */
    private function documentChain(Document $doc): array
    {
        $chain = [(string) $doc->id];
        $parentId = $doc->parent_id;
        $depth = 30;

        while ($parentId && $depth-- > 0 && ! in_array($parentId, $chain, true)) {
            $chain[] = (string) $parentId;
            $parentId = Document::query()->where('id', $parentId)->value('parent_id');
        }

        return $chain;
    }
}
