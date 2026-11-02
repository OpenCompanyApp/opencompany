<?php

namespace App\Domain\Routines\Builtins;

use App\Models\Routine;

/**
 * A code-defined routine (kind `builtin`): system plumbing that used to be a
 * hardcoded cron job, now scheduled and triggered like every other routine
 * (docs/planning/connections-events-routines.md, "Proactivity as
 * configuration").
 *
 * Contract:
 *  - built-ins run without an LLM and without an agent; they must not call
 *    agent tools or widen any permission;
 *  - `run()` is invoked by RunBuiltinRoutineJob with the routine's workspace
 *    bound as `currentWorkspace`, and must only touch that workspace;
 *  - `run()` must be idempotent per schedule slot (the tick dispatches each
 *    slot once, but a queue may redeliver);
 *  - the routine row is system-managed: BuiltinRoutineRegistry creates it for
 *    applicable workspaces and derives `is_active` from applicability, so
 *    users cannot edit or delete it through the routine API or tools.
 */
interface BuiltinRoutine
{
    /** Stable key stored in `routines.built_in_key`, e.g. `telegram.digest`. */
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /** Cron expression (UTC) the routine row is provisioned with. */
    public function cronExpression(): string;

    /**
     * Workspaces this built-in currently applies to. Rows in other workspaces
     * are deactivated so the tick does not record pointless trigger events.
     *
     * @return array<int, string>
     */
    public function applicableWorkspaceIds(): array;

    /**
     * Execute one run for `$routine->workspace_id`.
     *
     * @return array<string, mixed> Summary stored as the routine's last result
     *
     * @throws \Throwable when the run failed; the job records the failure
     */
    public function run(Routine $routine): array;
}
