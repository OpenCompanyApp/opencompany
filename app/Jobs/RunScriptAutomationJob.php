<?php

namespace App\Jobs;

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
 * Queue transport for one script automation run.
 *
 * Script execution lives in the Automations domain context. This adapter owns
 * queue uniqueness, timeout/retry metadata, serialization, and workspace
 * binding for the sandbox execution path.
 *
 * The source receipt is captured when the job is constructed (dispatch time).
 * A payload without a receipt, or whose receipt no longer matches the stored
 * automation, never reaches the executor: queued work only runs the exact
 * Ruby source that was admitted when it was scheduled.
 */
class RunScriptAutomationJob implements ShouldBeUnique, ShouldQueue
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

    public function __construct(
        private Automation $automation,
        ?string $dispatchKey = null,
        ?array $sourceReceipt = null,
    ) {
        $this->dispatchKey = $dispatchKey ?? (string) Str::uuid();
        $this->sourceReceipt = $sourceReceipt ?? ScriptAutomationInvocation::capture($automation);
    }

    public function uniqueId(): string
    {
        return 'script_automation_'.$this->automation->id;
    }

    public function middleware(): array
    {
        return $this->agentExecutionMiddleware((string) $this->automation->agent_id, 90);
    }

    public function handle(?ExecuteScriptAutomation $executeScriptAutomation = null): void
    {
        $current = Automation::query()->whereKey($this->automation->id)->first();
        if ($this->sourceReceipt === null || $current === null
            || ! ScriptAutomationInvocation::matches($current, $this->sourceReceipt)) {
            return;
        }

        $this->setWorkspaceContext($this->automation->workspace_id);

        try {
            ($executeScriptAutomation ?? app(ExecuteScriptAutomation::class))->handle(
                $this->automation,
                $this->dispatchKey,
                $this->job?->uuid(),
                $this->sourceReceipt,
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
                safeBroadcast(new TaskUpdated($task, 'failed'), 'terminal script automation queue failure');
            }
            $run->agent?->resolveIdleStatus();
        } finally {
            $this->clearWorkspaceContext();
        }
    }
}
