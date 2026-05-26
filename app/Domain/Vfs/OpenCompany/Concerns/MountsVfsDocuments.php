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
 */
trait MountsVfsDocuments
{
    /**
     * @return list<VfsEntry>
     */
    private function listDocs(User $agent, array $segments, string $path, VfsBudget $budget): array
    {
        if (count($segments) === 1) {
            $entries = [
                new VfsEntry('by-id', '/docs/by-id', 'directory', capabilities: ['browse']),
            ];
            $remaining = max(0, $budget->maxEntries - count($entries));
            if ($remaining === 0) {
                return $entries;
            }

            $docs = Document::forWorkspace()
                ->whereNull('parent_id')
                ->where('is_system', false)
                ->orderByDesc('is_folder')
                ->orderBy('title')
                ->limit($remaining)
                ->get();

            foreach ($docs as $doc) {
                if ($this->canAccessDocument($agent, $doc)) {
                    $entries[] = $this->documentEntry($agent, $doc, '/docs/'.$this->slug($doc->title));
                }
            }

            return $entries;
        }

        if (($segments[1] ?? null) === 'by-id') {
            if (! isset($segments[2])) {
                return Document::forWorkspace()
                    ->where('is_system', false)
                    ->orderByDesc('is_folder')
                    ->orderBy('title')
                    ->limit($budget->maxEntries)
                    ->get()
                    ->filter(fn (Document $doc): bool => $this->canAccessDocument($agent, $doc))
                    ->map(fn (Document $doc): VfsEntry => $this->documentEntry($agent, $doc, "/docs/by-id/{$doc->id}"))
                    ->values()
                    ->all();
            }

            $doc = $this->documentFromByIdSegments($segments, $path);
            if (! $doc->is_folder) {
                throw VfsError::notReadable($path);
            }

            return $this->documentChildren($agent, $doc, "/docs/by-id/{$doc->id}", $budget);
        }

        $doc = $this->resolveDocumentPath(array_slice($segments, 1), $path);
        if (! $doc->is_folder) {
            throw VfsError::notReadable($path);
        }

        return $this->documentChildren($agent, $doc, $path, $budget);
    }

    private function readDocs(User $agent, array $segments, string $path, VfsBudget $budget): string
    {
        if (count($segments) === 1) {
            return $this->entriesToText($this->listDocs($agent, $segments, $path, $budget));
        }

        $doc = (($segments[1] ?? null) === 'by-id' && isset($segments[2]))
            ? $this->documentFromByIdSegments($segments, $path)
            : $this->resolveDocumentPath(array_slice($segments, 1), $path);

        if (! $this->canAccessDocument($agent, $doc)) {
            throw VfsError::permissionDenied($path, 'document folder scope');
        }
        if ($doc->is_system) {
            throw VfsError::permissionDenied($path, 'system document');
        }

        if ($doc->is_folder) {
            return $this->entriesToText($this->documentChildren($agent, $doc, $path, $budget));
        }

        return VfsText::truncate((string) $doc->content, $budget->maxBytes);
    }

    /**
     * @return list<VfsEntry>
     */
    private function documentChildren(User $agent, Document $folder, string $path, VfsBudget $budget): array
    {
        if (! $this->canAccessDocument($agent, $folder)) {
            throw VfsError::permissionDenied($path, 'document folder scope');
        }

        return Document::forWorkspace()
            ->where('parent_id', $folder->id)
            ->where('is_system', false)
            ->orderByDesc('is_folder')
            ->orderBy('title')
            ->limit($budget->maxEntries)
            ->get()
            ->filter(fn (Document $doc): bool => $this->canAccessDocument($agent, $doc))
            ->map(fn (Document $doc): VfsEntry => $this->documentEntry($agent, $doc, rtrim($path, '/').'/'.$this->slug($doc->title)))
            ->values()
            ->all();
    }

    private function documentEntry(User $agent, Document $doc, string $path): VfsEntry
    {
        return new VfsEntry(
            name: $doc->title,
            path: $path,
            type: $doc->is_folder ? 'directory' : 'file',
            canonicalPath: "/docs/by-id/{$doc->id}",
            backendType: 'document',
            backendId: $doc->id,
            version: $this->version($doc->updated_at?->toIso8601String(), $doc->content ?? ''),
            capabilities: $doc->is_folder ? ['browse', 'read', 'search'] : array_values(array_filter(['read', 'search', $this->canPatchDocument($agent, $doc) ? 'patch' : null])),
            metadata: [
                'title' => $doc->title,
                'is_system' => $doc->is_system,
                'content_format' => $doc->content_format ?? 'markdown',
                'updated_at' => $doc->updated_at?->toIso8601String(),
            ],
        );
    }
}
