<?php

namespace App\Console\Commands;

use App\Events\TaskUpdated;
use App\Jobs\AgentRespondJob;
use App\Jobs\RunAutomationJob;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Task;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repairs agent-run state after worker death or interrupted Telegram handoff.
 *
 * The command never retries opaque failed_jobs rows. It operates on scoped,
 * typed AgentRunRecord data so reconciliation cannot replay another workspace's
 * side effects or revive a cancelled/terminal task.
 */
class ReconcileAgentRuns extends Command
{
    protected $signature = 'agent-runs:reconcile';

    protected $description = 'Fail stale logical runs and recover stranded Telegram lane handoffs';

    public function handle(): int
    {
        $reconciled = 0;
        $terminalTasks = $this->reconcileTasksFromTerminalRuns();
        $runningCutoff = now()->subSeconds((int) config('agent_runtime.stale_running_after_seconds'));
        $queuedCutoff = now()->subSeconds((int) config('agent_runtime.stale_queued_after_seconds'));
        $recoveredAutomations = $this->recoverAutomationDispatches($queuedCutoff);

        AgentRunRecord::query()
            ->with('task')
            ->where(function ($query) use ($runningCutoff, $queuedCutoff): void {
                $query->where(function ($running) use ($runningCutoff): void {
                    $running->whereIn('status', [AgentRunRecord::STATUS_RUNNING, AgentRunRecord::STATUS_RETRYING])
                        ->where('heartbeat_at', '<', $runningCutoff);
                })->orWhere(function ($queued) use ($queuedCutoff): void {
                    $queued->where('status', AgentRunRecord::STATUS_QUEUED)
                        ->where('heartbeat_at', '<', $queuedCutoff);
                });
            })
            ->orderBy('created_at')
            ->chunkById(100, function ($runs) use (&$reconciled): void {
                foreach ($runs as $run) {
                    DB::transaction(function () use ($run, &$reconciled): void {
                        $locked = AgentRunRecord::query()->with('task')->lockForUpdate()->find($run->id);
                        if (! $locked || $locked->isTerminal()) {
                            return;
                        }

                        $task = $locked->task;
                        if (! $task) {
                            $locked->markFailed('Run had no task during stale reconciliation.');

                            return;
                        }

                        if ($task->isTerminal()) {
                            $locked->reconcileFromTerminalTask();

                            return;
                        }

                        $telegramQueue = $task->context['telegram_lane_queue'] ?? null;
                        if ($locked->status === AgentRunRecord::STATUS_QUEUED
                            && $task->status === Task::STATUS_PENDING
                            && is_array($telegramQueue)
                            && empty($telegramQueue['dispatched_at'])) {
                            return;
                        }

                        if ($task->status === Task::STATUS_PAUSED) {
                            $locked->markWaiting();

                            return;
                        }

                        $reason = 'Agent run stopped heartbeating before completion.';
                        $locked->markFailed($reason);
                        $task->fail($reason);
                        $task->addStep($reason, 'action')->complete();
                        safeBroadcast(new TaskUpdated($task, 'failed'), 'stale agent run');
                        $reconciled++;
                    });
                }
            });

        $recovered = $this->recoverTelegramLaneClaims();
        $this->info("Reconciled {$reconciled} stale run(s) and {$terminalTasks} terminal task mismatch(es); recovered {$recoveredAutomations} automation dispatch(es) and {$recovered} Telegram lane task(s).");

        return self::SUCCESS;
    }

    /**
     * Complete the second half of a terminal run/task transition interrupted
     * by worker death. This never revives or changes an already-terminal Task.
     */
    private function reconcileTasksFromTerminalRuns(): int
    {
        $count = 0;
        AgentRunRecord::query()
            ->whereIn('status', [
                AgentRunRecord::STATUS_COMPLETED,
                AgentRunRecord::STATUS_FAILED,
                AgentRunRecord::STATUS_CANCELLED,
            ])
            ->whereHas('task', fn ($query) => $query->whereNotIn('status', [
                Task::STATUS_COMPLETED,
                Task::STATUS_FAILED,
                Task::STATUS_CANCELLED,
            ]))
            ->with('task')
            ->chunkById(100, function ($runs) use (&$count): void {
                foreach ($runs as $run) {
                    DB::transaction(function () use ($run, &$count): void {
                        $locked = AgentRunRecord::query()->with('task')->lockForUpdate()->find($run->id);
                        if (! $locked?->isTerminal() || ! $locked->task || $locked->task->isTerminal()) {
                            return;
                        }

                        $locked->reconcileTaskFromTerminalRun();
                        $task = $locked->task->fresh() ?? $locked->task;
                        safeBroadcast(new TaskUpdated($task, $task->status), 'terminal run task reconciliation');
                        $count++;
                    });
                }
            });

        return $count;
    }

    /**
     * Recover the crash window between a schedule claim and physical dispatch.
     *
     * The due command creates the Task/AgentRunRecord before enqueueing. A
     * stale queued automation run is therefore a typed outbox entry with a
     * stable dispatch key, not an opaque failed_jobs payload.
     */
    private function recoverAutomationDispatches(Carbon $queuedCutoff): int
    {
        $candidates = AgentRunRecord::query()
            ->where('status', AgentRunRecord::STATUS_QUEUED)
            ->whereIn('kind', [
                AgentRunRecord::KIND_PROMPT_AUTOMATION,
                AgentRunRecord::KIND_SCRIPT_AUTOMATION,
            ])
            ->where('heartbeat_at', '<', $queuedCutoff)
            ->with('task')
            ->oldest()
            ->get();

        $recovered = 0;
        foreach ($candidates as $candidate) {
            $automationId = $candidate->metadata['automation_id'] ?? $candidate->task?->context['automation_id'] ?? null;
            $automation = Automation::query()
                ->where('workspace_id', $candidate->workspace_id)
                ->find($automationId);
            if (! $automation || ! $candidate->dispatch_key || $candidate->task?->status !== Task::STATUS_PENDING) {
                continue;
            }

            $run = DB::transaction(function () use ($candidate, $queuedCutoff): ?AgentRunRecord {
                $run = AgentRunRecord::query()->lockForUpdate()->find($candidate->id);
                if (! $run || $run->status !== AgentRunRecord::STATUS_QUEUED || $run->heartbeat_at?->gte($queuedCutoff)) {
                    return null;
                }

                $run->update([
                    'heartbeat_at' => now(),
                    'metadata' => array_merge($run->metadata ?? [], [
                        'dispatching_at' => now()->toIso8601String(),
                    ]),
                ]);

                return $run;
            });

            if (! $run) {
                continue;
            }

            try {
                app()->instance('currentWorkspace', Workspace::query()->findOrFail($run->workspace_id));
                RunAutomationJob::dispatch($automation, $run->dispatch_key);
                $run->update([
                    'metadata' => array_merge($run->metadata ?? [], [
                        'dispatching_at' => null,
                        'recovered_dispatch_at' => now()->toIso8601String(),
                    ]),
                ]);
                $recovered++;
            } catch (\Throwable $exception) {
                Log::warning('Automation dispatch reconciliation failed', [
                    'agent_run_id' => $run->id,
                    'automation_id' => $automation->id,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                app()->forgetInstance('currentWorkspace');
            }
        }

        return $recovered;
    }

    private function recoverTelegramLaneClaims(): int
    {
        $claimCutoff = now()->subSeconds((int) config('agent_runtime.telegram_dispatch_claim_seconds'));
        $candidates = Task::query()
            ->where('source', Task::SOURCE_CHAT)
            ->where('status', Task::STATUS_PENDING)
            ->whereNotNull('trigger_message_id')
            ->whereHas('channel', fn ($query) => $query->where('external_provider', 'telegram'))
            ->with(['channel', 'agent', 'triggerMessage'])
            ->oldest()
            ->get()
            ->filter(function (Task $task) use ($claimCutoff): bool {
                $queue = $task->context['telegram_lane_queue'] ?? null;
                if (! is_array($queue) || ! empty($queue['dispatched_at'])) {
                    return false;
                }

                $claim = isset($queue['dispatching_at']) ? Carbon::parse($queue['dispatching_at']) : null;

                return $claim === null || $claim->lt($claimCutoff);
            })
            ->groupBy(fn (Task $task) => $task->workspace_id.':'.$task->channel_id)
            ->map->first();

        $recovered = 0;
        foreach ($candidates as $candidate) {
            $task = DB::transaction(function () use ($candidate): ?Task {
                Channel::query()->whereKey($candidate->channel_id)->lockForUpdate()->first();

                if (Task::query()
                    ->where('workspace_id', $candidate->workspace_id)
                    ->where('channel_id', $candidate->channel_id)
                    ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_PAUSED])
                    ->exists()) {
                    return null;
                }

                $task = Task::query()->with(['agent', 'triggerMessage'])
                    ->whereKey($candidate->id)
                    ->where('status', Task::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();
                if (! $task) {
                    return null;
                }

                $context = $task->context ?? [];
                $queue = $context['telegram_lane_queue'] ?? [];
                $queue['dispatching_at'] = now()->toIso8601String();
                $context['telegram_lane_queue'] = $queue;
                $task->update(['context' => $context]);

                return $task;
            });

            if (! $task?->agent || ! $task->triggerMessage || ! $task->channel_id) {
                continue;
            }

            try {
                app()->instance('currentWorkspace', Workspace::query()->findOrFail($task->workspace_id));
                AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_CHAT, 3);
                AgentRespondJob::dispatch($task->triggerMessage, $task->agent, $task->channel_id, $task->id);

                $context = $task->fresh()->context ?? [];
                $queue = $context['telegram_lane_queue'] ?? [];
                unset($queue['dispatching_at']);
                $queue['dispatched_at'] = now()->toIso8601String();
                $queue['recovered_at'] = now()->toIso8601String();
                $context['telegram_lane_queue'] = $queue;
                $task->update(['context' => $context]);
                $recovered++;
            } catch (\Throwable $exception) {
                Log::warning('Telegram lane reconciliation dispatch failed', [
                    'task' => $task->id,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                app()->forgetInstance('currentWorkspace');
            }
        }

        return $recovered;
    }
}
