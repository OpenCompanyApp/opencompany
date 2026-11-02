<?php

namespace App\Console\Commands;

use App\Domain\Events\Causality;
use App\Domain\Events\EventLog;
use App\Domain\Routines\Application\ResolveRoutineRun;
use App\Domain\Routines\Builtins\BuiltinRoutineRegistry;
use App\Jobs\RunBuiltinRoutineJob;
use App\Jobs\RunRoutineJob;
use App\Models\AgentRunRecord;
use App\Models\Routine;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The routine scheduler: the only cron-driven entry point for routines.
 *
 * For each due routine slot it, in one transaction holding the routine row
 * lock:
 *  1. records a `routine.triggered` event in the workspace event log, as the
 *     root of a new causality chain, with idempotency key
 *     `routine:{id}:{scheduledAt}`. If that event already exists the slot was
 *     already handled and nothing is dispatched again;
 *  2. computes the run's causality as the next hop from the event
 *     (`Causality::next()`), refusing past `events.max_depth`;
 *  3. for agent/script routines, creates the durable Task/AgentRunRecord pair
 *     carrying that causality (reconciliation can redispatch it if this
 *     process dies before the queue push);
 *  4. advances `next_run_at`.
 * After commit it dispatches RunRoutineJob (agent/script) or
 * RunBuiltinRoutineJob (built-in), carrying the causality.
 *
 * Invariant: a trigger is never consent. The tick grants nothing; runs get the
 * executing agent's ordinary permissions and approval policy.
 *
 * Scans all workspaces by design (scheduler path). Every write carries the
 * routine's explicit workspace id. Built-in rows are provisioned first so new
 * applicable workspaces join within one tick.
 */
class RoutinesTick extends Command
{
    protected $signature = 'routines:tick';

    protected $description = 'Record trigger events for due routines and dispatch their runs';

    public const EVENT_TYPE = 'routine.triggered';

    public function handle(EventLog $events, BuiltinRoutineRegistry $builtins): int
    {
        $builtins->sync();

        $dueIds = Routine::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->pluck('id');

        $dispatched = 0;

        foreach ($dueIds as $routineId) {
            try {
                /** @var array{routine: Routine, causality: Causality, dispatch_key: string|null}|null $claim */
                $claim = DB::transaction(fn (): ?array => $this->claim((string) $routineId, $events));
            } catch (\DomainException $exception) {
                // Includes CausalityDepthExceeded and a missing agent/channel.
                Routine::query()->find($routineId)?->recordFailure($exception->getMessage());
                Log::error('Due routine rejected before dispatch', [
                    'routine' => $routineId,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($claim === null) {
                continue;
            }

            $routine = $claim['routine'];
            if ($routine->isBuiltin()) {
                RunBuiltinRoutineJob::dispatch($routine, $claim['causality']);
            } else {
                RunRoutineJob::dispatch($routine, $claim['dispatch_key'], $claim['causality']);
            }
            $dispatched++;

            Log::info('Routine dispatched', [
                'routine' => $routine->id,
                'kind' => $routine->kind,
                'dispatch_key' => $claim['dispatch_key'],
                'causality_id' => $claim['causality']->id,
            ]);
        }

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} routine(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Claim one due slot. Runs inside the caller's transaction.
     *
     * @return array{routine: Routine, causality: Causality, dispatch_key: string|null}|null
     *
     * @throws \DomainException when the run cannot be created
     */
    private function claim(string $routineId, EventLog $events): ?array
    {
        $routine = Routine::query()
            ->whereKey($routineId)
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->lockForUpdate()
            ->first();

        if ($routine === null || $routine->next_run_at === null) {
            return null;
        }

        $scheduledAt = $routine->next_run_at->copy()->utc()->toIso8601String();
        $event = $events->record(
            workspaceId: $routine->workspace_id,
            type: self::EVENT_TYPE,
            source: 'scheduler',
            actor: ['type' => 'system'],
            payload: [
                'routine_id' => $routine->id,
                'routine_kind' => $routine->kind,
                'routine_version' => $routine->version,
                'scheduled_at' => $scheduledAt,
                'cron_expression' => $routine->cron_expression,
                'timezone' => $routine->timezone,
            ],
            subject: '/routines/'.$routine->id,
            idempotencyKey: "routine:{$routine->id}:{$scheduledAt}",
            sourceRef: $routine->id,
            occurredAt: $routine->next_run_at,
        );

        // Invariant: claim the slot while the row is locked, before anything
        // can throw below, so another scheduler skips it.
        $nextRunAt = $routine->computeNextRunAt();

        if (! $event->wasRecentlyCreated) {
            // This slot already produced its event (and therefore its run).
            $routine->updateQuietly(['next_run_at' => $nextRunAt]);

            return null;
        }

        // Loop protection: refuse the hop past the configured depth.
        $causality = $events->causalityOf($event)->next();

        $dispatchKey = null;
        if (! $routine->isBuiltin()) {
            $dispatchKey = (string) Str::uuid();
            app()->instance('currentWorkspace', Workspace::query()->findOrFail($routine->workspace_id));

            try {
                // Commit the typed run/task before physical dispatch. If the
                // command dies in the following narrow window,
                // agent-runs:reconcile can redispatch this exact key.
                app(ResolveRoutineRun::class)->handle(
                    $routine,
                    $dispatchKey,
                    $routine->isScript() ? AgentRunRecord::KIND_ROUTINE_SCRIPT : AgentRunRecord::KIND_ROUTINE_AGENT,
                    2,
                    $causality,
                );
            } finally {
                app()->forgetInstance('currentWorkspace');
            }
        }

        $routine->updateQuietly(['next_run_at' => $nextRunAt]);

        return ['routine' => $routine, 'causality' => $causality, 'dispatch_key' => $dispatchKey];
    }
}
