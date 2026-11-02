<?php

namespace App\Models\Concerns;

use App\Agents\Runtime\ActiveAgentRun;
use App\Models\ChangeRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records agent edits to governed content while an agent run is active, so
 * "revert this run" can undo them (governance-model.md "Change sets").
 *
 * Owns: capturing created / updated / trashed / restored changes with raw
 * before/after values of the changed columns. Human edits (no ActiveAgentRun
 * bound) are not recorded here. Purges (forceDelete) are not revertible and
 * are not recorded. Recording failures never block the edit itself.
 *
 * Workspace boundary: the record takes the model's workspace when it has one,
 * else the run's.
 */
trait RecordsAgentChanges
{
    /** @var list<string> Bookkeeping columns that never make a change worth reverting. */
    private static array $ignoredChangeColumns = ['updated_at', 'created_at'];

    public static function bootRecordsAgentChanges(): void
    {
        static::created(fn (Model $model) => self::recordAgentChange($model, 'created', null, $model->getAttributes()));

        static::updated(function (Model $model): void {
            $changes = array_diff_key($model->getChanges(), array_flip(self::$ignoredChangeColumns));
            // Soft delete / restore saves are recorded by their own events.
            unset($changes[method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : 'deleted_at']);
            if ($changes === []) {
                return;
            }
            $before = array_intersect_key($model->getRawOriginal(), $changes);
            self::recordAgentChange($model, 'updated', $before, array_intersect_key($model->getAttributes(), $changes));
        });

        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                self::recordAgentChange($model, 'deleted', null, null);
            }
        });

        // Requires a trash (Trashable): users implement RevertibleContent.
        static::restored(fn (Model $model) => self::recordAgentChange($model, 'restored', null, null));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private static function recordAgentChange(Model $model, string $operation, ?array $before, ?array $after): void
    {
        if (! app()->bound(ActiveAgentRun::class)) {
            return;
        }

        try {
            $run = app(ActiveAgentRun::class)->record();
            ChangeRecord::create([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $model->getAttribute('workspace_id') ?? $run->workspace_id,
                'run_id' => $run->id,
                'agent_id' => $run->agent_id,
                'subject_type' => $model::class,
                'subject_id' => (string) $model->getKey(),
                'operation' => $operation,
                'before' => $before,
                'after' => $after,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
