<?php

namespace App\Jobs;

use App\Domain\AgentRuntime\Application\ExecuteAssignedTask;
use App\Events\TaskUpdated;
use App\Jobs\Concerns\SerializesAgentExecution;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\AgentRunRecord;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAgentTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SerializesAgentExecution, SetsWorkspaceContext;

    /** Overlap releases are bounded by retryUntil(), not an attempt count. */
    public int $tries = 0;

    public int $maxRunAttempts = 3;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        private Task $task,
    ) {}

    public function middleware(): array
    {
        return $this->agentExecutionMiddleware((string) $this->task->agent_id);
    }

    public function handle(?ExecuteAssignedTask $executeAssignedTask = null): void
    {
        $this->setWorkspaceContext($this->task->workspace_id);

        try {
            $executeAssignedTask ??= app(ExecuteAssignedTask::class);
            $executeAssignedTask->handle($this->task, $this->job?->uuid(), $this->maxRunAttempts);
        } finally {
            $this->clearWorkspaceContext();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->setWorkspaceContext($this->task->workspace_id);

        try {
            $run = AgentRunRecord::ensureForTask($this->task, AgentRunRecord::KIND_ASSIGNED_TASK, $this->maxRunAttempts);
            if (! $run->isTerminal()) {
                $run->markFailed($exception);
            }
            if (! $this->task->isTerminal()) {
                $this->task->fail($exception->getMessage());
                safeBroadcast(new TaskUpdated($this->task, 'failed'), 'terminal assigned-task queue failure');
            }
            $this->task->agent?->resolveIdleStatus();
        } finally {
            $this->clearWorkspaceContext();
        }
    }
}
