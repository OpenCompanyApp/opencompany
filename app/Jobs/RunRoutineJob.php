<?php

namespace App\Jobs;

use App\Domain\Events\Causality;
use App\Domain\Routines\Application\ExecuteAgentRoutine;
use App\Domain\Routines\Application\ExecuteScriptRoutine;
use App\Events\TaskUpdated;
use App\Jobs\Concerns\SerializesAgentExecution;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\AgentRunRecord;
use App\Models\Routine;
use App\Services\ScriptRoutineInvocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Queue transport for one agent or script routine run.
 *
 * Agent and script execution live in the Routines domain context. Script
 * work is invoked directly from this adapter so it retains the already-held
 * per-agent lock; synchronously dispatching another locked job would deadlock.
 *
 * Ruby (mruby) script runs capture a source receipt at dispatch time so a
 * queued run never executes code edited after it was scheduled, use a much
 * shorter timeout, and get exactly one semantic attempt (see
 * ExecuteScriptRoutine). Overlap releases still do not burn that attempt.
 *
 * The optional causality is the chain of the event that triggered the run
 * (`routines:tick` computes the next hop). It is forwarded to the executor,
 * which stamps it on the run's task when the pair is first created. Built-in
 * routines never use this job; see RunBuiltinRoutineJob.
 */
class RunRoutineJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SerializesAgentExecution, SetsWorkspaceContext;

    /** Overlap releases are bounded by retryUntil(), not an attempt count. */
    public int $tries = 0;

    public int $maxRunAttempts = 2;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [30];

    public int $uniqueFor = 3700;

    private string $dispatchKey;

    /** @var array<string, mixed>|null Source/runtime receipt for script routines. */
    private ?array $scriptReceipt = null;

    public function __construct(
        private Routine $routine,
        ?string $dispatchKey = null,
        private ?Causality $causality = null,
    ) {
        $this->dispatchKey = $dispatchKey ?? (string) Str::uuid();

        if ($routine->isScript()) {
            $this->scriptReceipt = ScriptRoutineInvocation::capture($routine);
            $this->timeout = (int) config('code.routine_job_timeout', 60);
        }
    }

    public function uniqueId(): string
    {
        return 'routine_'.$this->routine->id;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return $this->agentExecutionMiddleware((string) $this->routine->agent_id);
    }

    public function handle(?ExecuteAgentRoutine $executeAgentRoutine = null): void
    {
        // Built-in routines have no agent and never reach the LLM runtime.
        if ($this->routine->isBuiltin()) {
            return;
        }

        if ($this->routine->isScript() || $this->scriptReceipt !== null) {
            if ($this->scriptReceipt === null) {
                // A script payload without a receipt fails closed rather
                // than running whatever source is current now.
                return;
            }
            $this->setWorkspaceContext($this->routine->workspace_id);

            try {
                app(ExecuteScriptRoutine::class)->handle(
                    $this->routine,
                    $this->dispatchKey,
                    $this->job?->uuid(),
                    $this->scriptReceipt,
                    $this->causality,
                );
            } finally {
                $this->clearWorkspaceContext();
            }

            return;
        }

        $this->setWorkspaceContext($this->routine->workspace_id);

        try {
            ($executeAgentRoutine ?? app(ExecuteAgentRoutine::class))->handle(
                $this->routine,
                $this->dispatchKey,
                $this->job?->uuid(),
                $this->maxRunAttempts,
                $this->causality,
            );
        } finally {
            $this->clearWorkspaceContext();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->setWorkspaceContext($this->routine->workspace_id);

        try {
            $run = AgentRunRecord::query()
                ->where('workspace_id', $this->routine->workspace_id)
                ->where('dispatch_key', $this->dispatchKey)
                ->first();
            if (! $run) {
                return;
            }

            if (! $run->isTerminal()) {
                $run->markFailed($exception);
            }
            $task = $run->task;
            if ($task && ! $task->isTerminal()) {
                $task->fail($exception->getMessage());
                safeBroadcast(new TaskUpdated($task, 'failed'), 'terminal routine queue failure');
            }
            $run->agent?->resolveIdleStatus();
        } finally {
            $this->clearWorkspaceContext();
        }
    }
}
