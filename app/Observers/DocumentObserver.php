<?php

namespace App\Observers;

use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;

class DocumentObserver
{
    /**
     * Auto-index when a non-folder document is saved/updated.
     */
    public function saved(Document $document): void
    {
        if ($document->is_folder) {
            return;
        }

        $collection = $this->resolveCollection($document);
        $agentId = $this->resolveAgentId($document);

        IndexDocumentJob::dispatch($document, $collection, $agentId);
    }

    /**
     * Remove all chunks when a document is deleted.
     */
    public function deleted(Document $document): void
    {
        DocumentChunk::where('document_id', $document->id)->delete();
    }

    /**
     * Resolve collection type based on document's folder hierarchy.
     * - agents/{slug}/memory/* → 'memory'
     * - agents/{slug}/identity/* → 'identity'
     * - everything else → 'general'
     */
    private function resolveCollection(Document $document): string
    {
        $parent = $document->parent;

        while ($parent) {
            if ($parent->is_folder) {
                if ($parent->title === 'memory') {
                    return 'memory';
                }
                if ($parent->title === 'identity') {
                    return 'identity';
                }
            }
            $parent = $parent->parent;
        }

        return 'general';
    }

    /**
     * Resolve agent owner if this document lives under agents/{slug}/.
     */
    private function resolveAgentId(Document $document): ?string
    {
        $parent = $document->parent;

        while ($parent) {
            if ($parent->parent?->title === 'agents' && $parent->parent?->parent_id === null) {
                $agent = User::where('type', 'agent')
                    ->whereRaw("LOWER(REPLACE(name, ' ', '-')) = ?", [strtolower($parent->title)])
                    ->first();

                return $agent?->id;
            }
            $parent = $parent->parent;
        }

        return null;
    }
}
