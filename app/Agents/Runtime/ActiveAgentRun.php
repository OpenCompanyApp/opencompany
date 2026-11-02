<?php

namespace App\Agents\Runtime;

use App\Models\AgentRunRecord;
use App\Models\Task;
use App\Models\TaskStep;

/**
 * Run-local cancellation and heartbeat handle shared with agent tools.
 *
 * Queue workers reuse Laravel's container. Callers must bind this object only
 * around one live run and forget it in a finally block. Tools use it to stop
 * before another external mutation when a user cancels a task mid-generation.
 */
class ActiveAgentRun
{
    private float $lastCheckpointAt = 0.0;

    private ?int $providerToolSequence = null;

    private int $internalToolSequence = 0;

    public function __construct(public readonly string $runId) {}

    public function record(): AgentRunRecord
    {
        return AgentRunRecord::query()->findOrFail($this->runId);
    }

    public function checkpoint(bool $force = false): void
    {
        if (! $force && microtime(true) - $this->lastCheckpointAt < 5.0) {
            return;
        }

        $run = $this->record();

        if ($run->cancellationRequested()) {
            throw new AgentRunCancelled("Agent run [{$run->id}] was cancelled.");
        }

        $task = $run->task;
        if ($task?->isTerminal()) {
            throw new AgentRunCancelled("Agent run [{$run->id}] was superseded by terminal task state [{$task->status}].");
        }

        if ($task?->status === Task::STATUS_PAUSED) {
            throw new AgentRunPaused("Agent run [{$run->id}] was paused.");
        }

        $run->heartbeat();
        $this->lastCheckpointAt = microtime(true);
    }

    /**
     * Return the next provider-visible tool position for this attempt.
     *
     * Resumed runs inject completed checkpoint TaskSteps into model history, so
     * those calls must be skipped when assigning sequence numbers. A completed
     * ledger call whose post-call checkpoint was never written is deliberately
     * not counted; the retry will encounter and safely replay that same slot.
     */
    public function nextProviderToolSequence(): int
    {
        if ($this->providerToolSequence === null) {
            $run = $this->record();
            $this->providerToolSequence = $run->task?->steps()
                ->where('status', TaskStep::STATUS_COMPLETED)
                ->whereJsonContains('metadata->checkpointed', true)
                ->count() ?? 0;
        }

        return ++$this->providerToolSequence;
    }

    /**
     * Code Mode script routines do not pass through Laravel AI tool events. Their
     * bridge calls therefore use an independent ordered replay surface.
     */
    public function nextInternalToolSequence(): int
    {
        return ++$this->internalToolSequence;
    }
}
