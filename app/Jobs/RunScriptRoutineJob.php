<?php

namespace App\Jobs;

use App\Domain\Events\Causality;
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
 * Queue transport for one script routine run.
 *
 * Script execution lives in the Routines domain context. This adapter owns
 * queue uniqueness, timeout/retry metadata, serialization, and workspace
 * binding for the sandbox execution path.
 *
 * The source receipt is captured when the job is constructed (dispatch time).
 * A payload without a receipt, or whose receipt no longer matches the stored
 * routine, never reaches the executor: queued work only runs the exact
 * Ruby source that was admitted when it was scheduled.
 */
class RunScriptRoutineJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SerializesAgentExecution, SetsWorkspaceContext;

    /** Overlap releases are bounded by retryUntil(), not an attempt count. */
    public int $tries = 0;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [10];

    public int $uniqueFor = 120;

    private string $dispatchKey;

    /** @var array<string, mixed>|null */
    private ?array $sourceReceipt = null;

    /** @param  array<string, mixed>|null  $sourceReceipt  Receipt captured at dispatch; computed now when omitted */
    public function __construct(
        private Routine $routine,
        ?string $dispatchKey = null,
        ?array $sourceReceipt = null,
        private ?Causality $causality = null,
    ) {
        $this->dispatchKey = $dispatchKey ?? (string) Str::uuid();
        $this->sourceReceipt = $sourceReceipt ?? ScriptRoutineInvocation::capture($routine);
    }

    public function uniqueId(): string
    {
        return 'routine_script_'.$this->routine->id;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return $this->agentExecutionMiddleware((string) $this->routine->agent_id, 90);
    }

    public function handle(?ExecuteScriptRoutine $executeScriptRoutine = null): void
    {
        $current = Routine::query()->whereKey($this->routine->id)->first();
        if ($this->sourceReceipt === null || $current === null
            || ! ScriptRoutineInvocation::matches($current, $this->sourceReceipt)) {
            return;
        }

        $this->setWorkspaceContext($this->routine->workspace_id);

        try {
            ($executeScriptRoutine ?? app(ExecuteScriptRoutine::class))->handle(
                $this->routine,
                $this->dispatchKey,
                $this->job?->uuid(),
                $this->sourceReceipt,
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
                safeBroadcast(new TaskUpdated($task, 'failed'), 'terminal script routine queue failure');
            }
            $run->agent?->resolveIdleStatus();
        } finally {
            $this->clearWorkspaceContext();
        }
    }
}
