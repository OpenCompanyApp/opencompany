<?php

namespace App\Services;

use App\Models\Routine;

/**
 * Pins queue intent to an admitted source and its workspace/actor authority.
 *
 * This receipt is transport metadata, not a second execution journal. Existing
 * routine Tasks remain the durable run claim and effect/result authority.
 * Legacy payloads without a receipt fail closed; queue rehydration must never
 * silently substitute a newly edited script for the one that was dispatched.
 */
final class ScriptRoutineInvocation
{
    /** @return array<string, string|null> No source bodies or credentials enter this receipt. */
    public static function capture(Routine $routine): array
    {
        return [
            'runtime' => $routine->script_runtime,
            'source' => hash('sha256', (string) $routine->script),
            'admitted_source' => $routine->script_digest,
            'engine' => $routine->script_engine_digest,
            'workspace' => $routine->workspace_id,
            'agent' => $routine->agent_id,
            'routine' => $routine->id,
        ];
    }

    /**
     * A stale receipt is rejected without disabling or modifying the newer source.
     *
     * @param  array<string, mixed>|null  $receipt
     */
    public static function matches(Routine $routine, ?array $receipt): bool
    {
        return $routine->isScript()
            && $routine->is_active
            && $receipt !== null
            && ($receipt['runtime'] ?? null) === config('code.runtime')
            && $receipt === self::capture($routine);
    }
}
