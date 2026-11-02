<?php

namespace App\Jobs;

use App\Domain\Automations\Application\ExecutePromptAutomation;
use App\Domain\Automations\Application\ExecuteScriptAutomation;
use App\Events\TaskUpdated;
use App\Jobs\Concerns\SerializesAgentExecution;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Services\ScriptAutomationInvocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Queue transport for one scheduled automation run.
 *
 * Prompt and script execution live in the Automations domain context. Script
 * work is invoked directly from this adapter so it retains the already-held
 * per-agent lock; synchronously dispatching another locked job would deadlock.
 *
 * Ruby (mruby) script runs capture a source receipt at dispatch time so a
 * queued run never executes code edited after it was scheduled, use a much
 * shorter timeout, and get exactly one semantic attempt (see
 * ExecuteScriptAutomation). Overlap releases still do not burn that attempt.
 */
class RunAutomationJob implements ShouldBeUnique, ShouldQueue
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

    /** @var array<string, mixed>|null Source/runtime receipt for script automations. */
    private ?array $scriptReceipt = null;

    public function __construct(
        private Automation $automation,
        ?string $dispatchKey = null,
    ) {
        $this->dispatchKey = $dispatchKey ?? (string) Str::uuid();

        if ($automation->isScript()) {
            $this->scriptReceipt = ScriptAutomationInvocation::capture($automation);
            $this->timeout = (int) config('code.automation_job_timeout', 60);
        }
    }

    public function uniqueId(): string
    {
        return 'automation_'.$this->automation->id;
    }

    public function middleware(): array
    {
        return $this->agentExecutionMiddleware((string) $this->automation->agent_id);
    }

    public function handle(?ExecutePromptAutomation $executePromptAutomation = null): void
    {
        if ($this->automation->isScript() || $this->scriptReceipt !== null) {
            if ($this->scriptReceipt === null) {
                // Payloads serialized before source receipts existed fail
                // closed rather than running whatever source is current now.
                return;
            }
            $this->setWorkspaceContext($this->automation->workspace_id);

            try {
                app(ExecuteScriptAutomation::class)->handle(
                    $this->automation,
                    $this->dispatchKey,
                    $this->job?->uuid(),
                    $this->scriptReceipt,
                );
            } finally {
                $this->clearWorkspaceContext();
            }

            return;
        }

        $this->setWorkspaceContext($this->automation->workspace_id);

        try {
            ($executePromptAutomation ?? app(ExecutePromptAutomation::class))->handle(
                $this->automation,
                $this->dispatchKey,
                $this->job?->uuid(),
                $this->maxRunAttempts,
            );
        } finally {
            $this->clearWorkspaceContext();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->setWorkspaceContext($this->automation->workspace_id);

        try {
            $run = AgentRunRecord::query()
                ->where('workspace_id', $this->automation->workspace_id)
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
                safeBroadcast(new TaskUpdated($task, 'failed'), 'terminal automation queue failure');
            }
            $run->agent?->resolveIdleStatus();
        } finally {
            $this->clearWorkspaceContext();
        }
    }
}
