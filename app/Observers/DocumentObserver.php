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
     * - agents/{slug}/identity/* → 'identity'
     * - agents/{slug}/memory/topics/* → 'topic'
     * - agents/{slug}/memory/logs/* → 'memory'
     * - agents/{slug}/memory/peers/* → 'peer'
     * - agents/{slug}/memory/MEMORY.md → 'memory'
     * - everything else → 'general'
     */
    private function resolveCollection(Document $document): string
    {
        $parent = $document->parent;
        $ancestry = [];

        while ($parent) {
            if ($parent->is_folder) {
                $ancestry[] = $parent->title;
            }
            $parent = $parent->parent;
        }

        // Direct child of identity/ folder
        if (in_array('identity', $ancestry)) {
            return 'identity';
        }

        // Check memory sub-folders
        if (in_array('topics', $ancestry)) {
            return 'topic';
        }

        if (in_array('logs', $ancestry)) {
            return 'memory';
        }

        if (in_array('peers', $ancestry)) {
            return 'peer';
        }

        // Direct child of memory/ folder (e.g., MEMORY.md or legacy logs)
        if (in_array('memory', $ancestry)) {
            return 'memory';
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
            if ($parent->parent?->title === 'agents' && $parent->parent?->parent_id === null) { // @phpstan-ignore nullsafe.neverNull
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
