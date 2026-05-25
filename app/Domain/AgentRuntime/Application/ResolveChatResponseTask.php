<?php

namespace App\Domain\AgentRuntime\Application;

use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolves the durable Task row for one chat response attempt.
 *
 * Chat response retries, delegation callbacks, and direct user messages all
 * converge on the same task lifecycle. Keeping that resolution separate makes
 * the response runner focus on LLM execution and delivery.
 */
class ResolveChatResponseTask
{
    public function handle(Message $userMessage, User $agent, string $channelId, ?string $taskId, int $attempts): Task
    {
        if ($taskId) {
            $task = Task::find($taskId);
            if ($task) {
                $task->isPending() ? $task->start() : $task->resume();

                return $task;
            }
        }

        $task = Task::where('agent_id', $agent->id)
            ->where('channel_id', $channelId)
            ->whereIn('source', [Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK, Task::SOURCE_AGENT_NOTIFY])
            ->where('status', Task::STATUS_PENDING)
            ->latest('created_at')
            ->first();

        if ($task) {
            $task->start();

            return $task;
        }

        $task = Task::where('agent_id', $agent->id)
            ->where('trigger_message_id', $userMessage->id)
            ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_FAILED])
            ->first();

        if ($task) {
            $task->update(['status' => Task::STATUS_ACTIVE, 'started_at' => now()]);
            Log::info('Reusing existing task from previous attempt', [
                'task' => $task->id,
                'agent' => $agent->name,
                'attempt' => $attempts,
            ]);

            return $task;
        }

        return Task::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $agent->workspace_id,
            'title' => Str::limit($userMessage->content, 80),
            'description' => $userMessage->content,
            'type' => Task::TYPE_CUSTOM,
            'status' => Task::STATUS_ACTIVE,
            'priority' => Task::PRIORITY_NORMAL,
            'source' => Task::SOURCE_CHAT,
            'agent_id' => $agent->id,
            'requester_id' => $userMessage->author_id,
            'channel_id' => $channelId,
            'trigger_message_id' => $userMessage->id,
            'started_at' => now(),
        ]);
    }
}
