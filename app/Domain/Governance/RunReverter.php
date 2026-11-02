<?php

namespace App\Domain\Governance;

use App\Domain\Events\Causality;
use App\Domain\Events\EventLog;
use App\Models\AgentRunRecord;
use App\Models\ChangeRecord;
use App\Models\Contracts\RevertibleContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * "Revert this run" (governance-model.md "Change sets"): undoes the recorded
 * changes of one agent run, newest first.
 *
 * Invariant — human edits always win: a change is only undone while the
 * affected values are still what the run left. A value someone changed since
 * is reported as a conflict and left alone; nothing is overwritten.
 *
 * Owns: applying the inverse of created (trash), updated (restore the before
 * values), deleted (restore from trash) and restored (trash again), stamping
 * each record as reverted by the human, and one `run.reverted` event on the
 * run's causality chain. Does not own authorization: callers check that the
 * human may act in the run's workspace.
 *
 * Only subject classes implementing RevertibleContent are touched.
 */
class RunReverter
{
    /**
     * @return array{reverted: int, conflicts: list<array{subject_type: string, subject_id: string, operation: string}>}
     */
    public function revert(AgentRunRecord $run, User $human): array
    {
        $reverted = 0;
        $conflicts = [];

        $records = ChangeRecord::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('run_id', $run->id)
            ->whereNull('reverted_at')
            ->orderByDesc('id') // UUIDv7: newest first
            ->get();

        foreach ($records as $record) {
            $applied = DB::transaction(fn (): bool => $this->undo($record));
            if (! $applied) {
                $conflicts[] = ['subject_type' => class_basename($record->subject_type), 'subject_id' => $record->subject_id, 'operation' => $record->operation];

                continue;
            }
            $record->update(['reverted_at' => now(), 'reverted_by_id' => $human->id]);
            $reverted++;
        }

        app(EventLog::class)->record(
            (string) $run->workspace_id,
            'run.reverted',
            'internal',
            ['type' => 'human', 'id' => (string) $human->id],
            payload: ['reverted' => $reverted, 'conflicts' => count($conflicts)],
            subject: 'run:'.$run->id,
            causality: $run->causality_id !== null ? new Causality((string) $run->causality_id, (int) $run->causality_depth) : null,
            runId: (string) $run->id,
        );

        return ['reverted' => $reverted, 'conflicts' => $conflicts];
    }

    /** Apply the inverse of one record; false on conflict or missing subject. */
    private function undo(ChangeRecord $record): bool
    {
        $class = $record->subject_type;
        if (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, RevertibleContent::class)) {
            return false;
        }

        $model = $class::query()->withoutGlobalScopes()->whereKey($record->subject_id)->lockForUpdate()->first();
        if (! $model instanceof RevertibleContent) {
            return false;
        }
        $trashed = $model->trashed();

        return match ($record->operation) {
            'created' => ! $trashed && $this->unchangedSince($model, $record->after ?? []) && (bool) $model->delete(),
            'updated' => ! $trashed && $this->unchangedSince($model, $record->after ?? []) && $this->restoreValues($model, $record->before ?? []),
            'deleted' => $trashed && (bool) $model->restore(),
            'restored' => ! $trashed && (bool) $model->delete(),
            default => false,
        };
    }

    /** @param array<string, mixed> $after */
    private function unchangedSince(Model $model, array $after): bool
    {
        $current = $model->getAttributes();
        foreach ($after as $column => $value) {
            if (in_array($column, ['created_at', 'updated_at'], true)) {
                continue;
            }
            if ($this->normalize($current[$column] ?? null) !== $this->normalize($value)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $before */
    private function restoreValues(Model $model, array $before): bool
    {
        $model->setRawAttributes(array_merge($model->getAttributes(), $before));

        return $model->save();
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }
}
