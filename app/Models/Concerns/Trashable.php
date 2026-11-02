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
 * Tree-shaped models (documents and list folders use `parent_id`) trash and
 * restore their descendants with them, so a trashed folder never leaves
 * visible orphans. Descendants are matched on the parent's exact
 * `deleted_at`, so restoring a folder does not resurrect children that were
 * trashed separately before it.
 *
 * The root row fires ordinary model events (deleted/restored/saved), so
 * observers handle it. Descendants are updated in bulk without events;
 * models override {@see onTrashed()}/{@see onRestored()} to apply the same
 * side effects (search chunks, ...) to them.
 */
trait Trashable
{
    use SoftDeletes;

    public static function bootTrashable(): void
    {
        static::softDeleted(function (self $model): void {
            $model->trashDescendants();
        });

        static::restoring(function (self $model): void {
            // Capture before SoftDeletes clears the column.
            $model->trashedAtBeforeRestore = $model->getAttribute($model->getDeletedAtColumn());
        });

        static::restored(function (self $model): void {
            $model->restoreDescendants();
        });
    }

    /** @var mixed deleted_at captured in `restoring` */
    protected mixed $trashedAtBeforeRestore = null;

    protected function onTrashed(): void {}

    protected function onRestored(): void {}

    private function trashDescendants(): void
    {
        $trashedAt = $this->getAttribute($this->getDeletedAtColumn());
        $descendants = $this->subtree(fn ($query) => $query);
        if ($descendants === []) {
            return;
        }

        // One stamp for the whole subtree, written without per-row events, so
        // restore can tell this cascade apart from earlier, separate deletions.
        static::withTrashed()->whereKey(array_keys($descendants))->toBase()
            ->update([$this->getDeletedAtColumn() => $trashedAt]);
        foreach ($descendants as $descendant) {
            $descendant->onTrashed();
        }
    }

    private function restoreDescendants(): void
    {
        if ($this->trashedAtBeforeRestore === null) {
            return;
        }

        $column = $this->getDeletedAtColumn();
        $stamp = $this->trashedAtBeforeRestore;
        $descendants = $this->subtree(fn ($query) => $query->onlyTrashed()->where($column, $stamp));
        if ($descendants === []) {
            return;
        }

        static::withTrashed()->whereKey(array_keys($descendants))->toBase()->update([$column => null]);
        foreach ($descendants as $descendant) {
            $descendant->onRestored();
        }
    }

    /**
     * Live (untrashed) descendants that trashing this row would take along.
     *
     * @return array<string, static>
     */
    protected function liveDescendants(): array
    {
        return $this->subtree(fn ($query) => $query);
    }

    /**
     * Breadth-first descendants by `parent_id`, bounded so a corrupt cyclic
     * tree cannot loop forever. Models without `parent_id` have none.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder<static>): mixed  $constrain
     * @return array<string, static>
     */
    private function subtree(callable $constrain): array
    {
        if (! in_array('parent_id', $this->getFillable(), true)) {
            return [];
        }

        $found = [];
        $frontier = [$this->getKey()];
        for ($depth = 0; $frontier !== [] && $depth < 64; $depth++) {
            $query = static::query()->whereIn('parent_id', $frontier);
            $constrain($query);
            $frontier = [];
            foreach ($query->get() as $child) {
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
