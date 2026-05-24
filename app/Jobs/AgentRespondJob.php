<?php

namespace App\Jobs;

use App\Domain\AgentRuntime\Application\RespondToChatMessage;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\Message;
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
    use SetsWorkspaceContext;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    /**
     * The number of seconds the unique lock should be maintained.
     */
    public int $uniqueFor = 1800;

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

    public function failed(\Throwable $exception): void
    {
        Log::error('AgentRespondJob failed', [
            'message_id' => $this->userMessage->id ?? null,
            'agent' => $this->agent->name ?? null,
            'channel' => $this->channelId,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Bind workspace context, then hand off response orchestration to the
     * AgentRuntime application service.
     */
    public function handle(?RespondToChatMessage $respondToChatMessage = null): void
    {
        $this->setWorkspaceContext($this->agent->workspace_id);

        ($respondToChatMessage ?? app(RespondToChatMessage::class))->handle(
            $this->userMessage,
            $this->agent,
            $this->channelId,
            $this->taskId,
            $this->attempts(),
            $this->tries,
        );
    }
}
