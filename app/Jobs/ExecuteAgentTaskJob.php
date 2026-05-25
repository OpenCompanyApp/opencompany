<?php

namespace App\Jobs;

use App\Domain\AgentRuntime\Application\ExecuteAssignedTask;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteAgentTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SetsWorkspaceContext;

    public int $tries = 3;

    public int $timeout = 1800;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        private Task $task,
    ) {}

    public function handle(?ExecuteAssignedTask $executeAssignedTask = null): void
    {
        $this->setWorkspaceContext($this->task->workspace_id);

        $executeAssignedTask ??= app(ExecuteAssignedTask::class);

        $executeAssignedTask->handle($this->task);
    }
}
