<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trash semantics for governed content (docs/architecture/governance-model.md
 * "Trash and purge"): `delete()` moves a row to trash (soft delete) and
 * trashed rows leave queries, search and agent context through the
 * SoftDeletes global scope. `forceDelete()` is the purge, a separate
 * destructive action gated by the preset (ActionClassifier::PURGE).
 *
 * Tree-shaped models (documents and list folders use `parent_id`) ALSO use
 * {@see TrashesInBatches}, which owns the subtree cascade: one
 * `trash_batch_id` is stamped on the root and every live descendant, and
 * restore brings back exactly that batch. Breadcrumb for future agents: do
 * not reintroduce a cascade keyed on `deleted_at` here — timestamps are
 * second-resolution, so restoring a folder would resurrect children that were
 * trashed separately within the same second (VFS integrity review, 2026-11).
 * The batch cascade trashes/restores each descendant through normal model
 * events, so observers (search indexing, ...) apply to descendants too.
 */
trait Trashable
{
    use SoftDeletes;

    /**
     * Live (untrashed) descendants that trashing this row would take along.
     * Used by guards that must refuse a trash before anything changes (for
     * example Document refuses to trash a folder containing system files).
     *
     * @return array<string, static>
     */
    protected function liveDescendants(): array
    {
        if (! in_array('parent_id', $this->getFillable(), true)) {
            return [];
        }

        $found = [];
        $frontier = [$this->getKey()];
        // Bounded so a corrupt cyclic tree cannot loop forever.
        for ($depth = 0; $frontier !== [] && $depth < 64; $depth++) {
            $children = static::query()->whereIn('parent_id', $frontier)->get();
            $frontier = [];
            foreach ($children as $child) {
                $key = (string) $child->getKey();
                if (! isset($found[$key])) {
                    $found[$key] = $child;
                    $frontier[] = $key;
                }
            }
        }

        return $found;
    }
}
