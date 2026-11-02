<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trash cascade and batch restore for tree-shaped soft-deleting models
 * (Document folders, ListItem projects).
 *
 * Why this lives on the model: before these models soft-deleted, removing a
 * folder physically DELETEd it and the `parent_id` foreign-key cascade removed
 * the subtree. A soft delete is an UPDATE, so without this trait every caller
 * that still just calls `$folder->delete()` (API controllers, domain tools)
 * would leave the children live, readable by id and orphaned. Owning the
 * cascade here means no caller can forget it.
 *
 * Contract:
 * - A soft delete (`delete()`, never `forceDelete()`) stamps the record with a
 *   fresh `trash_batch_id` (and `deleted_by` when the caller supplied one via
 *   trashAsBatch()), then trashes every still-live child with the same batch
 *   id, recursively. Children trashed earlier on their own keep their own
 *   batch and are not touched.
 * - `restore()` brings back exactly the descendants that carry the restored
 *   record's batch id, recursively, and clears batch id and `deleted_by`.
 *   Something trashed independently stays in the trash.
 * - The cascade runs in the `trashed` event, i.e. after the record's own
 *   delete passed every `deleting` guard (Document refuses system documents).
 *   It is not atomic by itself: callers wrap the delete in a transaction,
 *   which trashAsBatch() does.
 *
 * Requires the SoftDeletes trait and `parent_id`, `deleted_by`,
 * `trash_batch_id` columns. Workspace scope: descendants are found through
 * `parent_id` only, which never crosses workspaces.
 */
trait TrashesInBatches
{
    /** Batch id the next soft delete of this instance stamps (set by trashAsBatch() or a parent's cascade). */
    private ?string $pendingTrashBatchId = null;

    /** Actor the next soft delete of this instance records in `deleted_by`. */
    private ?string $pendingTrashedBy = null;

    /** Batch id captured while this instance is being restored. */
    private ?string $restoringTrashBatchId = null;

    public static function bootTrashesInBatches(): void
    {
        static::softDeleted(function (Model $model): void {
            /** @var Model&self $model */
            $model->cascadeTrashBatch();
        });

        static::restoring(function (Model $model): void {
            /** @var Model&self $model */
            $model->restoringTrashBatchId = $model->getAttribute('trash_batch_id');
            // Saved together with the cleared deleted_at by restore().
            $model->setAttribute('trash_batch_id', null);
            $model->setAttribute('deleted_by', null);
        });

        static::restored(function (Model $model): void {
            /** @var Model&self $model */
            $model->restoreTrashBatchChildren();
        });
    }

    /**
     * Move this record (and its live subtree) to the trash as one batch.
     *
     * @param  string|null  $deletedBy  actor id recorded on every batch member
     * @param  string|null  $batchId  caller-chosen batch id (generated when null)
     * @return string the batch id
     */
    public function trashAsBatch(?string $deletedBy = null, ?string $batchId = null): string
    {
        $batchId ??= (string) Str::uuid();

        DB::transaction(function () use ($deletedBy, $batchId): void {
            $this->pendingTrashBatchId = $batchId;
            $this->pendingTrashedBy = $deletedBy;
            try {
                $this->delete();
            } finally {
                $this->pendingTrashBatchId = null;
                $this->pendingTrashedBy = null;
            }
        });

        return $batchId;
    }

    private function cascadeTrashBatch(): void
    {
        $batchId = $this->pendingTrashBatchId ?? (string) Str::uuid();
        // Callers that know the actor pass it; otherwise the signed-in human.
        $deletedBy = $this->pendingTrashedBy ?? (auth()->check() ? (string) auth()->id() : null);

        // Base query: SoftDeletes' delete only writes deleted_at, and a model
        // save here would fire the saved hooks (search indexing) for a row
        // that is already in the trash.
        static::withTrashed()->whereKey($this->getKey())->toBase()->update([
            'trash_batch_id' => $batchId,
            'deleted_by' => $deletedBy,
        ]);
        $this->setAttribute('trash_batch_id', $batchId);
        $this->setAttribute('deleted_by', $deletedBy);
        $this->syncOriginalAttributes(['trash_batch_id', 'deleted_by']);

        // Default scope excludes already-trashed children: they keep their own batch.
        foreach (static::query()->where('parent_id', $this->getKey())->get() as $child) {
            /** @var Model&self $child */
            $child->pendingTrashBatchId = $batchId;
            $child->pendingTrashedBy = $deletedBy;
            $child->delete();
        }
    }

    private function restoreTrashBatchChildren(): void
    {
        $batchId = $this->restoringTrashBatchId;
        $this->restoringTrashBatchId = null;
        if ($batchId === null) {
            return;
        }

        foreach (static::onlyTrashed()->where('parent_id', $this->getKey())->where('trash_batch_id', $batchId)->get() as $child) {
            $child->restore();
        }
    }
}
