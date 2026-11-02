<?php

namespace App\Domain\AgentRuntime\Application;

use App\Models\AgentRunRecord;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskStep;

/**
 * Detects whether a chat response has already been delivered.
 *
 * Delivery is the irreversible side effect in a chat turn. This service keeps
 * retry idempotency rules explicit so the runtime can repair bookkeeping
 * without sending duplicate visible messages.
 */
class DetectDeliveredChatResponse
{
    public function handle(Task $task, AgentRunRecord $run): bool
    {
        $hasDeliveredResponse = $task->steps()
            ->where('description', 'Response delivered')
            ->where('status', TaskStep::STATUS_COMPLETED)
            ->exists();

        if ($hasDeliveredResponse) {
            return $hasDeliveredResponse;
        }

        return Message::query()->where('agent_run_id', $run->id)->exists();
    }
}
