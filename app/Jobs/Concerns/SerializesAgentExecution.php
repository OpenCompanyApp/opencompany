<?php

namespace App\Jobs\Concerns;

use DateTimeInterface;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Serializes all logical run types for one agent on the shared physical queue.
 *
 * The lock key is shared across chat, assigned-task, and routine job classes.
 * Lock-contention releases may increase Laravel's physical attempt counter, so
 * logical provider attempts are tracked separately on AgentRunRecord.
 */
trait SerializesAgentExecution
{
    /**
     * Bound physical retries by wall-clock time rather than attempt count.
     *
     * WithoutOverlapping releases consume Laravel attempts. A busy agent can
     * legitimately hold its lock for the full 1,800-second runtime, so a small
     * tries value would fail later work before it ever executes. The queue
     * serializes this deadline into the original payload; semantic provider
     * attempts remain independently capped on AgentRunRecord.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addSeconds((int) config('agent_runtime.physical_retry_window_seconds'));
    }

    /** @return list<WithoutOverlapping> */
    protected function agentExecutionMiddleware(string $agentId, int $expiresAfter = 1900): array
    {
        return [
            (new WithoutOverlapping("agent-execution:{$agentId}"))
                ->shared()
                ->releaseAfter(5)
                ->expireAfter($expiresAfter),
        ];
    }
}
