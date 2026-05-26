<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Models\Document;
use App\Models\User;

/**
 * Central document authorization rules for all document-backed VFS mounts.
 *
 * Documents surface through /docs directly and through agent memory/files
 * aliases. Keeping the permission contract here prevents those mounts from
 * drifting on folder-scope semantics or accidentally making system documents
 * writable.
 */
trait AuthorizesVfsDocuments
{
    private function canAccessDocument(User $agent, Document $doc): bool
    {
        $allowedFolderIds = $this->permissions->getAllowedFolderIds($agent);
        if ($allowedFolderIds === null) {
            return true;
        }

        if ($doc->is_folder && in_array($doc->id, $allowedFolderIds, true)) {
            return true;
        }

        return $this->documentIsDescendantOf($doc, $allowedFolderIds);
    }

    private function canPatchDocument(User $agent, Document $doc): bool
    {
        if ($doc->is_folder || $doc->is_system) {
            return false;
        }

        $allowedFolderIds = $this->permissions->getAllowedFolderIds($agent);

        return $allowedFolderIds !== null && $this->canAccessDocument($agent, $doc);
    }

    /**
     * @param  list<string>  $folderIds
     */
    private function documentIsDescendantOf(Document $doc, array $folderIds): bool
    {
        $parentId = $doc->parent_id;
        $depth = 30;

        while ($parentId && $depth-- > 0) {
            if (in_array($parentId, $folderIds, true)) {
                return true;
            }

            $parentId = Document::query()->where('id', $parentId)->value('parent_id');
        }

        return false;
    }
}
