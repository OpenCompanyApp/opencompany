<?php

namespace App\Domain\Routines\Application;

use App\Domain\Events\Causality;
use App\Models\AgentRunRecord;
use App\Models\Channel;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolves one durable task/run pair for a serialized routine dispatch.
 *
 * The dispatch key is created when the run is claimed and remains stable
 * across physical retries. This prevents every retry from creating a fresh
 * active task and makes output correlation exact.
 *
 * Owns: stamping the run's causality chain onto the Task and AgentRunRecord
 * when the pair is first created. The caller computes the hop (normally
 * `EventLog::causalityOf($event)->next()` in `routines:tick`), so the depth
 * limit is enforced before anything durable exists. Manual runs without a
 * triggering event start a new root chain. An existing pair (redelivery,
 * reconciliation) keeps the causality it was created with.
 *
 * Only agent and script routines have runs here; built-in routines have no
 * agent and execute through RunBuiltinRoutineJob instead.
 */
class ResolveRoutineRun
{
    /**
     * @throws \DomainException when the agent or output channel is missing or
     *                          belongs to another workspace
     */
    public function handle(
        Routine $routine,
        string $dispatchKey,
        string $kind,
        int $maxAttempts,
        ?Causality $causality = null,
    ): RoutineRunContext {
        $agent = User::query()
            ->where('workspace_id', $routine->workspace_id)
            ->where('type', 'agent')
            ->find($routine->agent_id);

        if (! $agent) {
            throw new \DomainException('Routine agent is missing or outside the routine workspace.');
        }

        $channelId = $routine->ensureChannel();
        if ($channelId === '' || ! Channel::query()
            ->where('workspace_id', $routine->workspace_id)
            ->whereKey($channelId)
            ->exists()) {
            throw new \DomainException('Routine channel is missing or outside the routine workspace.');
        }

        return DB::transaction(function () use ($routine, $dispatchKey, $kind, $maxAttempts, $agent, $channelId, $causality): RoutineRunContext {
            $existing = AgentRunRecord::query()
                ->where('workspace_id', $routine->workspace_id)
                ->where('dispatch_key', $dispatchKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return new RoutineRunContext($agent, $existing->task()->firstOrFail(), $existing, $channelId);
            }

            // A manual run is a human action: it starts its own chain.
            $causality ??= Causality::root();
            $isScript = $kind === AgentRunRecord::KIND_ROUTINE_SCRIPT;
            $task = Task::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $routine->workspace_id,
                'title' => ($isScript ? 'Script: ' : 'Routine: ').$routine->name,
                'description' => $isScript ? Str::limit((string) $routine->script, 200) : $routine->instructions,
                'type' => Task::TYPE_CUSTOM,
                'status' => Task::STATUS_PENDING,
                'priority' => Task::PRIORITY_NORMAL,
                'source' => Task::SOURCE_ROUTINE,
                'agent_id' => $agent->id,
                'requester_id' => $routine->created_by_id,
                'channel_id' => $channelId,
                'causality_id' => $causality->id,
                'causality_depth' => $causality->depth,
                'context' => array_filter([
                    'routine_id' => $routine->id,
                    'routine_kind' => $routine->kind,
                    'routine_version' => $routine->version,
                    'schedule' => $routine->cron_expression,
                    'run_number' => $routine->run_count + 1,
                    'trigger_event_id' => $causality->parentEventId,
                ], fn ($value) => $value !== null),
            ]);

            $run = AgentRunRecord::query()->create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $routine->workspace_id,
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'channel_id' => $channelId,
                'kind' => $kind,
                'dispatch_key' => $dispatchKey,
                'status' => AgentRunRecord::STATUS_QUEUED,
                'max_attempts' => $maxAttempts,
                'heartbeat_at' => now(),
                'causality_id' => $causality->id,
                'causality_depth' => $causality->depth,
                'metadata' => ['routine_id' => $routine->id],
            ]);

            return new RoutineRunContext($agent, $task, $run, $channelId);
        });
    }
}
