<?php

namespace App\Domain\Routines\Builtins;

use App\Models\Routine;
use Illuminate\Support\Str;

/**
 * The code-defined built-in routines and the provisioning of their rows.
 *
 * Owns: the list of built-ins and `sync()`, which makes each applicable
 * workspace have exactly one active row per built-in key (unique on
 * `workspace_id` + `built_in_key`) and deactivates rows in workspaces the
 * built-in no longer applies to. Runs from `routines:tick` before due
 * routines are selected, so new workspaces are picked up within a minute.
 *
 * Does not own: execution (RunBuiltinRoutineJob) or scheduling semantics
 * (`routines:tick`). Add a new built-in by implementing BuiltinRoutine and
 * listing it in `definitions()`; never add a dedicated cron instead.
 */
class BuiltinRoutineRegistry
{
    /** @return array<string, BuiltinRoutine> keyed by built-in key */
    public function definitions(): array
    {
        $definitions = [app(TelegramDigestRoutine::class)];

        return collect($definitions)->keyBy(fn (BuiltinRoutine $routine): string => $routine->key())->all();
    }

    public function find(?string $key): ?BuiltinRoutine
    {
        return $key === null ? null : ($this->definitions()[$key] ?? null);
    }

    /**
     * Provision and (de)activate built-in rows across all workspaces.
     *
     * Cross-workspace by design: this is scheduler maintenance, not a request
     * path. Every row it writes carries an explicit workspace id.
     */
    public function sync(): void
    {
        foreach ($this->definitions() as $key => $definition) {
            $applicable = $definition->applicableWorkspaceIds();
            $existing = Routine::query()->where('built_in_key', $key)->get()->keyBy('workspace_id');

            foreach ($applicable as $workspaceId) {
                $row = $existing->get($workspaceId);
                if ($row === null) {
                    Routine::query()->create([
                        'id' => (string) Str::uuid(),
                        'workspace_id' => $workspaceId,
                        'kind' => Routine::KIND_BUILTIN,
                        'built_in_key' => $key,
                        'name' => $definition->name(),
                        'description' => $definition->description(),
                        'cron_expression' => $definition->cronExpression(),
                        'timezone' => 'UTC',
                        'mode' => Routine::MODE_ACT,
                        'notify' => Routine::NOTIFY_ON_FAILURE,
                        'is_active' => true,
                        // Due on the tick that provisions it; the built-in
                        // decides what (if anything) is actually due.
                        'next_run_at' => now(),
                    ]);
                } elseif (! $row->is_active) {
                    $row->update([
                        'is_active' => true,
                        'consecutive_failures' => 0,
                        'next_run_at' => $row->computeNextRunAt(),
                    ]);
                }
            }

            Routine::query()
                ->where('built_in_key', $key)
                ->where('is_active', true)
                ->whereNotIn('workspace_id', $applicable)
                ->update(['is_active' => false]);
        }
    }
}
