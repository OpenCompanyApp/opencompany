<?php

namespace App\Services;

use App\Models\Automation;

/**
 * Pins queue intent to an admitted source and its workspace/actor authority.
 *
 * This receipt is transport metadata, not a second execution journal. Existing
 * automation Tasks remain the durable run claim and effect/result authority.
 * Legacy payloads without a receipt fail closed; queue rehydration must never
 * silently substitute a newly edited script for the one that was dispatched.
 */
final class ScriptAutomationInvocation
{
    /** @return array<string, string|null> No source bodies or credentials enter this receipt. */
    public static function capture(Automation $automation): array
    {
        return [
            'runtime' => $automation->script_runtime,
            'source' => hash('sha256', (string) $automation->script),
            'admitted_source' => $automation->script_digest,
            'engine' => $automation->script_engine_digest,
            'workspace' => $automation->workspace_id,
            'agent' => $automation->agent_id,
            'automation' => $automation->id,
        ];
    }

    /** A stale receipt is rejected without disabling or modifying the newer source. */
    public static function matches(Automation $automation, ?array $receipt): bool
    {
        return $automation->isScript()
            && $automation->is_active
            && $receipt !== null
            && ($receipt['runtime'] ?? null) === config('code.runtime')
            && $receipt === self::capture($automation);
    }
}
