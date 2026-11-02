<?php

namespace App\Jobs;

use App\Domain\Automations\Application\ExecutePromptAutomation;
use App\Domain\Automations\Application\ExecuteScriptAutomation;
use App\Events\TaskUpdated;
use App\Jobs\Concerns\SerializesAgentExecution;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\AgentRunRecord;
use App\Models\Automation;
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

    public function __construct(
        private Automation $automation,
        ?string $dispatchKey = null,
    ) {
        $this->dispatchKey = $dispatchKey ?? (string) Str::uuid();
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
        if ($this->automation->isScript()) {
            $this->setWorkspaceContext($this->automation->workspace_id);

            try {
                app(ExecuteScriptAutomation::class)->handle(
                    $this->automation,
                    $this->dispatchKey,
                    $this->job?->uuid(),
                    $this->maxRunAttempts,
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
