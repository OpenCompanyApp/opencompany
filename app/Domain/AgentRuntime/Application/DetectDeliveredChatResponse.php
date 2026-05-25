<?php

namespace App\Domain\AgentRuntime\Application;

use App\Models\Message;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;

/**
 * Detects whether a chat response has already been delivered.
 *
 * Delivery is the irreversible side effect in a chat turn. This service keeps
 * retry idempotency rules explicit so the runtime can repair bookkeeping
 * without sending duplicate visible messages.
 */
class DetectDeliveredChatResponse
{
    public function handle(Task $task, Message $userMessage, User $agent, string $channelId, int $attempts): bool
    {
        $hasDeliveredResponse = $task->steps()
            ->where('description', 'Response delivered')
            ->where('status', TaskStep::STATUS_COMPLETED)
            ->exists();

        if ($hasDeliveredResponse || $attempts <= 1) {
            return $hasDeliveredResponse;
        }

        return Message::where('channel_id', $channelId)
            ->where('author_id', $agent->id)
            ->where('created_at', '>', $userMessage->created_at)
            ->where('created_at', '>', now()->subMinutes(30))
            ->whereNull('source')
            ->exists();
    }
}
