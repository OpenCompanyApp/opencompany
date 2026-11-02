<?php

namespace App\Jobs;

use App\Domain\AgentRuntime\Application\RespondToChatMessage;
use App\Events\TaskUpdated;
use App\Jobs\Concerns\SerializesAgentExecution;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\AgentRunRecord;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue transport for one durable agent chat response.
 *
 * The runtime behavior lives in the AgentRuntime domain context. This adapter
 * owns queue uniqueness, retry metadata, serialization, and workspace binding
 * so dispatch call sites do not need to know about those infrastructure
 * concerns.
 */
class AgentRespondJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SerializesAgentExecution, SetsWorkspaceContext;

    /**
     * The number of times the job may be attempted.
     */
    /** Overlap releases are bounded by retryUntil(), not an attempt count. */
    public int $tries = 0;

    /** Semantic provider attempts; overlap releases do not consume these. */
    public int $maxRunAttempts = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800;

    /** Hard timeouts cannot safely resume inside a killed PHP process. */
    public bool $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    /**
     * The number of seconds the unique lock should be maintained.
     */
    public int $uniqueFor = 3700;

    public function __construct(
        private Message $userMessage,
        private User $agent,
        private string $channelId,
        private ?string $taskId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->userMessage->id.':'.$this->agent->id;
    }

    public function middleware(): array
    {
        return $this->agentExecutionMiddleware($this->agent->id);
    }

    public function failed(\Throwable $exception): void
    {
        $this->setWorkspaceContext($this->agent->workspace_id);

        try {
            if ($this->taskId && ($task = Task::query()
                ->where('workspace_id', $this->agent->workspace_id)
                ->find($this->taskId))) {
                $run = AgentRunRecord::ensureForTask($task, AgentRunRecord::KIND_CHAT, $this->maxRunAttempts);
                if (! $run->isTerminal()) {
                    $run->markFailed($exception);
                }
                if (! $task->isTerminal()) {
                    $task->fail($exception->getMessage());
                    safeBroadcast(new TaskUpdated($task, 'failed'), 'terminal chat queue failure');
                }
            }
            $this->agent->resolveIdleStatus();

            Log::error('AgentRespondJob failed', [
                'message_id' => $this->userMessage->id ?? null,
                'agent' => $this->agent->name ?? null,
                'channel' => $this->channelId,
                'exception' => $exception->getMessage(),
            ]);
        } finally {
            $this->clearWorkspaceContext();
        }
    }

    /**
     * Bind workspace context, then hand off response orchestration to the
     * AgentRuntime application service.
     */
    public function handle(?RespondToChatMessage $respondToChatMessage = null): void
    {
        $this->setWorkspaceContext($this->agent->workspace_id);

        try {
            ($respondToChatMessage ?? app(RespondToChatMessage::class))->handle(
                $this->userMessage,
                $this->agent,
                $this->channelId,
                $this->taskId,
                queueJobUuid: $this->job?->uuid(),
                maxTries: $this->maxRunAttempts,
            );
        } finally {
            $this->clearWorkspaceContext();
        }
    }
}
