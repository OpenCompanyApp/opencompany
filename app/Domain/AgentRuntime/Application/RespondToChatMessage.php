<?php

namespace App\Domain\AgentRuntime\Application;

use App\Agents\Runtime\AgentRunBuilder;
use App\Agents\Runtime\AgentRunFailed;
use App\Agents\Runtime\AgentRunOptions;
use App\Domain\Ai\Usage\UsageRecorder;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Jobs\AgentRespondJob;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Services\AgentCommunicationService;
use App\Services\Memory\MemoryFlushService;
use App\Services\TelegramService;
use App\Support\TokenMetrics;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\StreamEvent;

/**
 * Application service that turns a user chat message into one durable agent response.
 *
 * This context owns task lifecycle, delivery idempotency, runtime-event
 * persistence, Telegram typing indicators, delegation callbacks, and
 * post-delivery bookkeeping. The queue job remains only the transport adapter
 * that binds workspace context and supplies retry metadata.
 */
class RespondToChatMessage
{
    /**
     * Stream event types consumed by the chat UI.
     *
     * Tool calls/results are persisted as task steps instead of being pushed
     * through the realtime message stream. Large tool results, especially Lua
     * documentation and integration payloads, can exceed the Pusher/Reverb
     * frame limit and must never be allowed to fail the durable agent run.
     */
    private const CHAT_STREAM_EVENT_TYPES = [
        'stream_start',
        'text_start',
        'text_delta',
        'text_end',
        'stream_end',
        'stream_failed',
    ];

    private ?string $telegramChatId = null;

    private Message $userMessage;

    private User $agent;

    private string $channelId;

    private ?string $taskId = null;

    private int $attempts = 1;

    private int $tries = 3;

    public function __construct(
        private ResolveChatResponseTask $tasks,
        private DetectDeliveredChatResponse $deliveredResponses,
        private DeliverAgentMessage $messageDelivery,
    ) {}

    /**
     * Execute one agent response turn for the given message.
     *
     * The caller is responsible for binding workspace context before invoking
     * this method. Attempt metadata is supplied by the queue adapter so retry
     * idempotency and final-attempt error delivery remain transport-aware.
     */
    public function handle(
        Message $userMessage,
        User $agent,
        string $channelId,
        ?string $taskId = null,
        int $attempts = 1,
        int $maxTries = 3,
    ): void {
        $this->userMessage = $userMessage;
        $this->agent = $agent;
        $this->channelId = $channelId;
        $this->taskId = $taskId;
        $this->attempts = max(1, $attempts);
        $this->tries = max(1, $maxTries);

        $task = $this->tasks->handle(
            $this->userMessage,
            $this->agent,
            $this->channelId,
            $this->taskId,
            $this->attempts(),
        );
        $this->taskId = $task->id;
        $hasDeliveredResponse = $this->deliveredResponses->handle(
            $task,
            $this->userMessage,
            $this->agent,
            $this->channelId,
            $this->attempts(),
        );

        if ($hasDeliveredResponse) {
            Log::info('Skipping retry - response already delivered', [
                'task' => $task->id,
                'agent' => $this->agent->name,
                'attempt' => $this->attempts(),
            ]);
            if (! $task->isTerminal()) {
                $task->complete(['response' => '(completed on previous attempt)']);
            }

            return;
        }

        safeBroadcast(new TaskUpdated($task, 'started'), 'task started');

        $responseDelivered = false;
        $llmStep = null;

        try {
            // Update agent status to working
            $this->agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($this->agent));

            // Start periodic typing indicator for Telegram channels
            $this->startTypingIndicator();

            // Step 1: Generate response
            $llmStep = $task->addStep('Generating response', 'action');

            $agentRun = app(AgentRunBuilder::class)->build(
                $this->agent,
                $this->channelId,
                $task->id,
                new AgentRunOptions(resumeFromTask: $this->attempts() > 1),
            );
            $agentInstance = $agentRun->agent();

            $currentMessages = [];

            // Capture LLM context before prompting. This snapshot is diagnostic
            // evidence: it lets us explain a bad answer without replaying the
            // run or reassembling prompts from mutable database state later.
            try {
                $currentMessages = $agentInstance->messages();
                $task->update(['context' => $agentRun->snapshot()]);
            } catch (\Throwable $e) {
                Log::warning('Failed to capture LLM context', ['error' => $e->getMessage()]);
            }

            // Memory flush: save important context to LTM before compaction
            try {
                $flushService = app(MemoryFlushService::class);
                if ($flushService->shouldFlush($this->channelId, $this->agent, $currentMessages, $agentInstance->fullInstructions())) {
                    $flushStep = $task->addStep('Flushing memories before compaction', 'action');
                    $flushStep->start();
                    $flushService->flush($this->channelId, $this->agent);
                    $flushStep->complete();
                }
            } catch (\Throwable $e) {
                Log::warning('Memory flush failed', ['error' => $e->getMessage()]);
            }

            $llmStep->start();
            $runResult = $agentRun->stream(
                $this->buildPromptWithThreadContext($this->userMessage),
                fn (StreamEvent $event) => $this->broadcastChatStreamEvent($event),
            );
            $response = $runResult->response;
            $responseText = $runResult->text;
            $context = $task->context ?? [];
            $context['runtime_events'] = array_map(fn ($event) => $event->toArray(), $runResult->events);
            $task->update(['context' => $context]);

            $lastStep = $response->steps->last();

            $llmStep->complete();

            // Mark agent as bootstrapped after first successful interaction
            if (! $this->agent->bootstrapped_at) {
                try {
                    $this->agent->update(['bootstrapped_at' => now()]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to set bootstrapped_at', ['error' => $e->getMessage()]);
                }
            }

            // Tool steps are now saved in real-time by CheckpointToolCall listener

            // Hold response if agent delegated work - wait for all results.
            // Check both the agent's tracking field AND the DB for active subtasks
            // to guard against a race where the subtask completes (and clears the
            // delegation ID) before we reach this point.
            $this->agent->refresh();
            $hasActiveSubtasks = Task::where('parent_task_id', $task->id)
                ->whereIn('source', [Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK])
                ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_FAILED, Task::STATUS_CANCELLED])
                ->exists();

            if (! empty($this->agent->awaiting_delegation_ids) || $hasActiveSubtasks) {
                $awaitingIds = $this->agent->awaiting_delegation_ids ?? [];
                $awaitingTasks = Task::whereIn('id', $awaitingIds)->with('agent')->get();
                $agentNames = $awaitingTasks->pluck('agent.name')->filter()->unique()->join(', ') ?: 'agents';
                $task->addStep(
                    "Awaiting results from {$agentNames}",
                    'action',
                    [
                        'icon' => 'ph:hourglass',
                        'awaiting' => $awaitingTasks->map(fn ($t) => [
                            'taskId' => $t->id,
                            'agentName' => $t->agent->name ?? 'Unknown',
                            'source' => $t->source,
                        ])->toArray(),
                    ]
                );

                Log::info('Agent holding response while awaiting delegations', [
                    'agent' => $this->agent->name,
                    'task' => $task->id,
                    'awaiting' => $this->agent->awaiting_delegation_ids,
                    'has_active_subtasks' => $hasActiveSubtasks,
                ]);

                // Task stays active, agent goes to awaiting_delegation (in finally block)
                return;
            }

            // Step 2: Deliver response
            $deliveryStep = $task->addStep('Response delivered', 'message');
            $deliveryStep->start();

            $this->messageDelivery->handle($this->agent, $this->userMessage, $this->channelId, $response, $responseText);

            $deliveryStep->complete();
            $responseDelivered = true;

            // Post-delivery bookkeeping.
            // Everything below must NOT cause a job retry (response is already sent).
            try {
                $llmStep->refresh();
                $metrics = TokenMetrics::fromResponse($response, $llmStep->started_at, $llmStep->completed_at);
                app(UsageRecorder::class)->record(
                    response: $response,
                    task: $task,
                    agent: $this->agent,
                    purpose: 'chat_response',
                    requestedProvider: $agentInstance->provider(),
                    requestedModel: $agentInstance->model(),
                    metrics: $metrics,
                );

                $task->complete(array_merge(
                    ['response' => $responseText],
                    $metrics,
                ));

                // Compute token breakdown from actual usage
                $promptTokens = $response->usage->promptTokens;
                $completionTokens = $response->usage->completionTokens;
                $lastStepPromptTokens = $lastStep?->usage->promptTokens ?? $promptTokens;

                $contextWindow = $task->context['context_window'] ?? 128_000;
                $outputReserve = (int) config('memory.compaction.output_reserve', 4_096);

                $systemChars = mb_strlen($task->context['system_prompt'] ?? '');
                $volatileChars = mb_strlen($task->context['volatile_prompt_context'] ?? '');
                $messageChars = array_sum(array_map(
                    fn ($m) => mb_strlen($m['content'] ?? ''),
                    $task->context['messages'] ?? [],
                ));
                $totalChars = $systemChars + $volatileChars + $messageChars;
                $systemRatio = $totalChars > 0 ? $systemChars / $totalChars : 0.5;
                $volatileRatio = $totalChars > 0 ? $volatileChars / $totalChars : 0.0;

                $systemTokens = (int) round($lastStepPromptTokens * $systemRatio);
                $volatileTokens = (int) round($lastStepPromptTokens * $volatileRatio);
                $messageTokens = max(0, $lastStepPromptTokens - $systemTokens - $volatileTokens);

                $available = max(0, $contextWindow - $systemTokens - $volatileTokens - $outputReserve);
                $thresholdRatio = (float) config('memory.compaction.threshold_ratio', 0.75);
                $safetyMargin = (float) config('memory.compaction.safety_margin', 1.2);
                $compactionThreshold = (int) ($available * $thresholdRatio);
                $adjustedTokens = (int) ($messageTokens * $safetyMargin);
                $remaining = max(0, $compactionThreshold - $adjustedTokens);
                $pctUsed = $compactionThreshold > 0
                    ? round(($adjustedTokens / $compactionThreshold) * 100, 1)
                    : 0;

                $context = $task->context;
                $context['token_breakdown'] = [
                    'context_window' => $contextWindow,
                    'output_reserve' => $outputReserve,
                    'system_prompt' => [
                        'total' => $systemTokens,
                        'sections' => $context['prompt_sections'] ?? [],
                    ],
                    'volatile_prompt_context' => [
                        'total' => $volatileTokens,
                        'sections' => $context['volatile_prompt_sections'] ?? [],
                    ],
                    'messages' => [
                        'total' => $messageTokens,
                        'count' => count($context['messages'] ?? []),
                    ],
                    'compaction' => [
                        'threshold' => $compactionThreshold,
                        'adjusted_tokens' => $adjustedTokens,
                        'remaining' => $remaining,
                        'pct_used' => $pctUsed,
                    ],
                    'actual_prompt_tokens' => $promptTokens,
                    'actual_completion_tokens' => $completionTokens,
                    'last_step_prompt_tokens' => $lastStepPromptTokens,
                    'finish_reason' => $lastStep?->finishReason->value ?? 'unknown',
                ];
                unset($context['prompt_sections'], $context['volatile_prompt_sections'], $context['context_window']);
                $task->update(['context' => $context]);
            } catch (\Throwable $e) {
                Log::warning('Post-delivery bookkeeping failed', ['error' => $e->getMessage(), 'task' => $task->id]);
                if (! $task->isTerminal()) {
                    $task->complete(['response' => $responseText]);
                }
            }

            // If this was a delegated task, send result back to the delegating agent
            if ($task->parent_task_id && in_array($task->source, [Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK])) {
                $this->handleDelegationCallback($task, $responseText);
            }

            safeBroadcast(new TaskUpdated($task, 'completed'), 'task completed');

            Log::info('Agent responded', [
                'agent' => $this->agent->name,
                'channel' => $this->channelId,
                'task' => $task->id,
                'tokens' => ($response->usage->promptTokens ?? 0) + ($response->usage->completionTokens ?? 0),
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof AgentRunFailed) {
                // AgentRunFailed carries runtime events and the context snapshot
                // captured before the provider error. Persist those details so
                // provider outages are diagnosable from the failed task record.
                $context = $task->context ?? [];
                $context['runtime_events'] = array_map(fn ($event) => $event->toArray(), $e->events);
                $task->update(['context' => array_merge($context, $e->contextSnapshot)]);
            }

            // If response was already delivered to the user, don't retry -
            // only post-delivery bookkeeping failed, which is non-critical.
            if ($responseDelivered) {
                Log::warning('Post-delivery error (not retrying - response already sent)', [
                    'agent' => $this->agent->name,
                    'task' => $task->id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            Log::error('Agent response failed', [
                'agent' => $this->agent->name,
                'channel' => $this->channelId,
                'task' => $task->id,
                'error' => $e->getMessage(),
            ]);

            if ($llmStep instanceof TaskStep) {
                $llmStep->refresh();

                if ($llmStep->isPending() || $llmStep->isInProgress()) {
                    $llmStep->skip();
                }
            }

            $errorStep = $task->addStep("Error: {$e->getMessage()}", 'action');
            $errorStep->start();
            $errorStep->complete();
            $task->fail($e->getMessage());

            // If this was a delegated/ask task, send error back to the requesting agent
            if ($task->parent_task_id && in_array($task->source, [Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK])) {
                $this->handleDelegationCallback($task, "Error: {$this->agent->name} failed to complete the task: {$e->getMessage()}");
            }

            safeBroadcast(new TaskUpdated($task, 'failed'), 'task failed');

            // Only notify user of error on the final attempt (avoid error + retry double message)
            if ($this->attempts() >= $this->tries) {
                $this->sendErrorMessage($e);
            }

            throw $e;
        } finally {
            $this->stopTypingIndicator();
            $this->agent->resolveIdleStatus();
        }
    }

    /**
     * Broadcast only the stream events that the chat message UI consumes.
     *
     * Runtime tool evidence is already saved by CheckpointToolCall and exposed
     * through task refreshes. Keeping large tool_result payloads out of the
     * websocket stream avoids Reverb/Pusher frame-limit failures while retaining
     * the durable task timeline.
     */
    private function broadcastChatStreamEvent(StreamEvent $event): void
    {
        $payload = $event->toArray();
        $type = (string) ($payload['type'] ?? '');

        if (! in_array($type, self::CHAT_STREAM_EVENT_TYPES, true)) {
            return;
        }

        try {
            Broadcast::on(new PrivateChannel('chat.'.$this->channelId))
                ->as($type)
                ->with($payload)
                ->sendNow();
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast agent stream event', [
                'agent' => $this->agent->name,
                'channel' => $this->channelId,
                'task' => $this->taskId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a typing indicator for Telegram channels.
     *
     * Note: We intentionally do NOT use SIGALRM for periodic typing because
     * it overrides the queue worker's timeout handler, breaking job timeouts
     * and causing jobs to be picked up by multiple workers.
     */
    private function startTypingIndicator(): void
    {
        try {
            $channel = Channel::find($this->channelId);
            if ($channel?->type !== 'external' || $channel->external_provider !== 'telegram' || ! $channel->external_id) {
                return;
            }

            $telegram = app(TelegramService::class);
            if (! $telegram->isConfigured()) {
                return;
            }

            $this->telegramChatId = $channel->external_id;
            $telegram->sendChatAction($this->telegramChatId);
        } catch (\Throwable) {
            // Non-critical
        }
    }

    /**
     * Stop the typing indicator (no-op now that SIGALRM is not used).
     */
    private function stopTypingIndicator(): void
    {
        $this->telegramChatId = null;
    }

    /**
     * Handle delegation callback: send result back to the delegating agent.
     */
    private function handleDelegationCallback(Task $subtask, string $resultText): void
    {
        try {
            $parentTask = $subtask->parentTask;
            if (! $parentTask) {
                Log::warning('Delegation callback: parent task not found', [
                    'subtask' => $subtask->id,
                    'parent_task_id' => $subtask->parent_task_id,
                ]);

                return;
            }

            // Don't resume a cancelled/completed parent
            if ($parentTask->isClosed()) {
                $parentAgent = User::find($parentTask->agent_id);
                $parentAgent?->removeAwaitingDelegation($subtask->id);
                Log::info('Delegation callback: parent task already closed, skipping resume', [
                    'subtask' => $subtask->id,
                    'parent_task_id' => $parentTask->id,
                    'parent_status' => $parentTask->status,
                ]);

                return;
            }

            $parentAgent = User::find($parentTask->agent_id);
            if (! $parentAgent) {
                Log::warning('Delegation callback: parent agent not found', [
                    'subtask' => $subtask->id,
                    'parent_agent_id' => $parentTask->agent_id,
                ]);

                return;
            }

            $commService = app(AgentCommunicationService::class);

            // Post result to agent-to-agent DM (observability)
            $dmChannelId = $commService->getOrCreateDmChannel($this->agent, $parentAgent);
            $commService->postMessage(
                $dmChannelId,
                $this->agent,
                $commService->formatResultMessage($this->agent, $subtask->id, $resultText),
                'delegation_result'
            );

            // Remove from parent's pending list
            $parentAgent->removeAwaitingDelegation($subtask->id);
            $parentAgent->refresh();

            // When ALL delegations are done, resume the parent agent's task
            if (empty($parentAgent->awaiting_delegation_ids)) {
                // Collect all completed subtask results
                $completedSubtasks = Task::where('parent_task_id', $parentTask->id)
                    ->whereIn('source', [Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK])
                    ->whereIn('status', [Task::STATUS_COMPLETED, Task::STATUS_FAILED])
                    ->get();

                $summary = $completedSubtasks->map(function (Task $st) {
                    $agent = User::find($st->agent_id);
                    $agentName = ($agent ? $agent->name : 'Unknown');
                    $response = $st->result['response'] ?? ($st->result['error'] ?? 'Completed.');

                    return "--- {$agentName}'s response ---\n{$response}";
                })->join("\n\n");

                // Create synthetic message with parent as author (not child)
                $resultMessage = Message::create([
                    'id' => Str::uuid()->toString(),
                    'content' => $summary,
                    'channel_id' => $parentTask->channel_id,
                    'author_id' => $parentAgent->id,
                    'timestamp' => now(),
                    'source' => 'delegation_result',
                ]);

                // Resume parent task with delegation results
                AgentRespondJob::dispatch(
                    $resultMessage,
                    $parentAgent,
                    $parentTask->channel_id,
                    $parentTask->id
                );
            }
        } catch (\Throwable $e) {
            Log::error('Delegation callback failed', [
                'subtask' => $subtask->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Safety net: ensure delegation ID is always removed even on error/early-return.
            // The happy path (line above) removes it normally; this catches edge cases.
            $parentTask = $subtask->parentTask;
            $cleanupAgentId = $parentTask ? $parentTask->agent_id : $subtask->requester_id;
            if ($cleanupAgentId) {
                $cleanupAgent = User::find($cleanupAgentId);
                if ($cleanupAgent && in_array($subtask->id, $cleanupAgent->awaiting_delegation_ids ?? [])) {
                    $cleanupAgent->removeAwaitingDelegation($subtask->id);
                    Log::info('Delegation callback: safety-net cleanup removed delegation ID', [
                        'subtask' => $subtask->id,
                        'parent_agent' => $cleanupAgent->name,
                    ]);
                }
            }
        }
    }

    /**
     * Build the prompt with thread context when the message is a reply.
     */
    private function buildPromptWithThreadContext(Message $message): string
    {
        $prompt = $message->content;

        if (! $message->reply_to_id) {
            return $prompt;
        }

        $parent = $message->replyTo()->with('author')->first();
        if (! $parent) {
            return $prompt;
        }

        $authorName = $parent->author->name ?? 'Unknown';
        $time = $parent->created_at->format('Y-m-d H:i');
        $content = Str::limit($parent->content, 500);

        return "[Replying to {$authorName}'s message from {$time}: \"{$content}\"]\n\n{$prompt}";
    }

    /**
     * Send a user-friendly error message when the agent fails.
     */
    private function sendErrorMessage(\Throwable $e): void
    {
        try {
            $errorMessage = Message::create([
                'id' => Str::uuid()->toString(),
                'content' => "I encountered an error while processing your message. Please try again later.\n\n*Error: {$e->getMessage()}*",
                'channel_id' => $this->channelId,
                'author_id' => $this->agent->id,
                'timestamp' => now(),
            ]);

            broadcast(new MessageSent($errorMessage));
        } catch (\Throwable $broadcastError) {
            Log::error('Failed to send agent error message', [
                'error' => $broadcastError->getMessage(),
            ]);
        }
    }

    private function attempts(): int
    {
        return $this->attempts;
    }
}
