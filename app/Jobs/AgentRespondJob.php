<?php

namespace App\Jobs;

use App\Agents\OpenCompanyAgent;
use App\Events\AgentStatusUpdated;
use App\Events\MessageSent;
use App\Events\TaskUpdated;
use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Task;
use App\Models\User;
use App\Support\TokenMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Services\AgentCommunicationService;
use App\Services\AgentDocumentService;
use App\Services\Memory\ModelContextRegistry;
use App\Services\TelegramService;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentRespondJob implements ShouldQueue, ShouldBeUnique
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

    private ?string $telegramChatId = null;

    public function __construct(
        private Message $userMessage,
        private User $agent,
        private string $channelId,
        private ?string $taskId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->userMessage->id . ':' . $this->agent->id;
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

    public function handle(): void
    {
        $this->setWorkspaceContext($this->agent->workspace_id);

        $task = null;

        // Resume or start an existing task (pending from dispatch, or delegation callback)
        if ($this->taskId) {
            $task = Task::find($this->taskId);
            if ($task) {
                if ($task->isPending()) {
                    $task->start();
                } else {
                    $task->resume();
                }
            }
        }

        if (!$task) {
            // Check for a pending delegation/ask/notify task for this agent on this channel
            $task = Task::where('agent_id', $this->agent->id)
                ->where('channel_id', $this->channelId)
                ->whereIn('source', [Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK, Task::SOURCE_AGENT_NOTIFY])
                ->where('status', Task::STATUS_PENDING)
                ->latest('created_at')
                ->first();

            if ($task) {
                $task->start();
            } else {
                // Check for an existing task from the same message (crash recovery / retry)
                $task = Task::where('agent_id', $this->agent->id)
                    ->where('trigger_message_id', $this->userMessage->id)
                    ->whereIn('status', [Task::STATUS_ACTIVE, Task::STATUS_FAILED])
                    ->first();

                if ($task) {
                    // Reuse existing task from a previous attempt
                    $task->update(['status' => Task::STATUS_ACTIVE, 'started_at' => now()]);
                    Log::info('Reusing existing task from previous attempt', [
                        'task' => $task->id,
                        'agent' => $this->agent->name,
                        'attempt' => $this->attempts(),
                    ]);
                } else {
                    // Create a task to track this chat interaction
                    $task = Task::create([
                        'id' => Str::uuid()->toString(),
                        'workspace_id' => $this->agent->workspace_id,
                        'title' => Str::limit($this->userMessage->content, 80),
                        'description' => $this->userMessage->content,
                        'type' => Task::TYPE_CUSTOM,
                        'status' => Task::STATUS_ACTIVE,
                        'priority' => Task::PRIORITY_NORMAL,
                        'source' => Task::SOURCE_CHAT,
                        'agent_id' => $this->agent->id,
                        'requester_id' => $this->userMessage->author_id,
                        'channel_id' => $this->channelId,
                        'trigger_message_id' => $this->userMessage->id,
                        'started_at' => now(),
                    ]);
                }
            }
        }

        // Guard: skip retry if a response was already delivered for this task
        $hasDeliveredResponse = $task->steps()
            ->where('description', 'Response delivered')
            ->where('status', \App\Models\TaskStep::STATUS_COMPLETED)
            ->exists();

        // Also check for an existing agent message (covers edge case where
        // message was created but delivery step wasn't marked complete)
        if (! $hasDeliveredResponse && $this->attempts() > 1) {
            $hasDeliveredResponse = Message::where('channel_id', $this->channelId)
                ->where('author_id', $this->agent->id)
                ->where('created_at', '>', $this->userMessage->created_at)
                ->where('created_at', '>', now()->subMinutes(30))
                ->whereNull('source')
                ->exists();
        }

        if ($hasDeliveredResponse) {
            Log::info('Skipping retry — response already delivered', [
                'task' => $task->id,
                'agent' => $this->agent->name,
                'attempt' => $this->attempts(),
            ]);
            if (!$task->isTerminal()) {
                $task->complete(['response' => '(completed on previous attempt)']);
            }
            return;
        }

        safeBroadcast(new TaskUpdated($task, 'started'), 'task started');

        $responseDelivered = false;

        try {
            // Update agent status to working
            $this->agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($this->agent));

            // Start periodic typing indicator for Telegram channels
            $this->startTypingIndicator();

            // Step 1: Generate response
            $llmStep = $task->addStep('Generating response', 'action');

            // Create the agent and get a response (task context is in system prompt)
            $agentInstance = OpenCompanyAgent::for($this->agent, $this->channelId, $task->id);

            // On retry: inject checkpointed tool calls so the LLM continues from where it left off
            if ($this->attempts() > 1) {
                $agentInstance->resumeFrom($task->id);
            }

            // Capture LLM context before prompting (for observability)
            try {
                $toolRegistry = app(\App\Agents\Tools\ToolRegistry::class);
                $task->update([
                    'context' => [
                        'system_prompt' => $agentInstance->instructions(),
                        'messages' => collect($agentInstance->messages()) /** @phpstan-ignore argument.templateType */
                            ->map(fn ($m) => [
                                'role' => $m->role->value,
                                'content' => Str::limit($m->content ?? '', 2000),
                            ])->values()->toArray(),
                        'tools' => $toolRegistry->getToolSlugsForAgent($this->agent),
                        'model' => $agentInstance->model(),
                        'provider' => $agentInstance->provider(),
                        'prompt_sections' => $agentInstance->instructionsBreakdown(),
                        'context_window' => app(ModelContextRegistry::class)
                            ->getContextWindow($agentInstance->model()),
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to capture LLM context', ['error' => $e->getMessage()]);
            }

            // Memory flush: save important context to LTM before compaction
            try {
                $flushService = app(\App\Services\Memory\MemoryFlushService::class);
                $currentMessages = $agentInstance->messages();
                if ($flushService->shouldFlush($this->channelId, $this->agent, $currentMessages, $agentInstance->instructions())) {
                    $flushStep = $task->addStep('Flushing memories before compaction', 'action');
                    $flushStep->start();
                    $flushService->flush($this->channelId, $this->agent);
                    $flushStep->complete();
                }
            } catch (\Throwable $e) {
                Log::warning('Memory flush failed', ['error' => $e->getMessage()]);
            }

            $llmStep->start();
            $response = $agentInstance->prompt($this->buildPromptWithThreadContext($this->userMessage));

            $lastStep = $response->steps->last();

            // If any step was truncated (Length), concatenate text from all steps
            // (the Prism handler auto-continues, but toResponse() only returns the last step's text)
            $wasTruncated = $response->steps->contains(
                fn ($step) => $step->finishReason === FinishReason::Length
            );

            if ($wasTruncated) {
                $responseText = $response->steps
                    ->filter(fn ($step) => !empty($step->text))
                    ->pluck('text')
                    ->join('');

                Log::info('Agent response auto-continued after truncation', [
                    'agent' => $this->agent->name,
                    'channel' => $this->channelId,
                    'continuation_steps' => $response->steps->filter(
                        fn ($step) => $step->finishReason === FinishReason::Length
                    )->count(),
                ]);
            } else {
                $responseText = $response->text;
            }

            if (empty($responseText)) {
                $responseText = "I processed your request but didn't generate a text response.";
            }

            $llmStep->complete();

            // Clear bootstrap file after first successful interaction
            if (!$this->agent->bootstrapped_at) {
                try {
                    $docService = app(AgentDocumentService::class);
                    $docService->updateIdentityFile($this->agent, 'BOOTSTRAP', '');
                    $this->agent->update(['bootstrapped_at' => now()]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to clear bootstrap', ['error' => $e->getMessage()]);
                }
            }

            // Tool steps are now saved in real-time by CheckpointToolCall listener

            // Hold response if agent delegated work — wait for all results.
            // Check both the agent's tracking field AND the DB for active subtasks
            // to guard against a race where the subtask completes (and clears the
            // delegation ID) before we reach this point.
            $this->agent->refresh();
            $hasActiveSubtasks = Task::where('parent_task_id', $task->id)
                ->whereIn('source', [Task::SOURCE_AGENT_DELEGATION, Task::SOURCE_AGENT_ASK])
                ->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_FAILED, Task::STATUS_CANCELLED])
                ->exists();

            if (!empty($this->agent->awaiting_delegation_ids) || $hasActiveSubtasks) {
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

            // Create the agent's response message (in the same thread if replying)
            $agentMessage = Message::create([
                'id' => Str::uuid()->toString(),
                'content' => $responseText,
                'channel_id' => $this->channelId,
                'author_id' => $this->agent->id,
                'reply_to_id' => $this->userMessage->reply_to_id,
                'timestamp' => now(),
            ]);

            // Save generated images from tool results as message attachments
            $this->saveImageAttachments($response, $agentMessage);

            // Update channel timestamp
            Channel::where('id', $this->channelId)
                ->update(['last_message_at' => now()]);

            // Update DM timestamp if applicable
            DirectMessage::where('channel_id', $this->channelId)
                ->update(['last_message_at' => now()]);

            // Dispatch event (fires listeners like SyncToTelegram, then broadcasts)
            try {
                event(new MessageSent($agentMessage));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast agent message', [
                    'error' => $broadcastError->getMessage(),
                    'channel' => $this->channelId,
                ]);
            }

            $deliveryStep->complete();
            $responseDelivered = true;

            // ── Post-delivery bookkeeping ──
            // Everything below must NOT cause a job retry (response is already sent).
            try {
                $llmStep->refresh();
                $metrics = TokenMetrics::fromResponse($response, $llmStep->started_at, $llmStep->completed_at);

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
                $messageChars = array_sum(array_map(
                    fn ($m) => mb_strlen($m['content'] ?? ''),
                    $task->context['messages'] ?? [],
                ));
                $totalChars = $systemChars + $messageChars;
                $systemRatio = $totalChars > 0 ? $systemChars / $totalChars : 0.5;

                $systemTokens = (int) round($lastStepPromptTokens * $systemRatio);
                $messageTokens = $lastStepPromptTokens - $systemTokens;

                $available = max(0, $contextWindow - $systemTokens - $outputReserve);
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
                unset($context['prompt_sections'], $context['context_window']);
                $task->update(['context' => $context]);
            } catch (\Throwable $e) {
                Log::warning('Post-delivery bookkeeping failed', ['error' => $e->getMessage(), 'task' => $task->id]);
                if (!$task->isTerminal()) {
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
            // If response was already delivered to the user, don't retry —
            // only post-delivery bookkeeping failed, which is non-critical.
            if ($responseDelivered) {
                Log::warning('Post-delivery error (not retrying — response already sent)', [
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

            $task->addStep("Error: {$e->getMessage()}", 'action');
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
     * Save generated images from tool call results as message attachments.
     */
    private function saveImageAttachments(AgentResponse $response, Message $message): void
    {
        try {
            foreach ($response->steps as $step) {
                foreach ($step->toolResults as $toolResult) {
                    if (!in_array($toolResult->name, ['RenderSvg', 'RenderMermaid', 'RenderPlantUml', 'RenderTypst', 'RenderVegaLite'])) {
                        continue;
                    }

                    $result = $toolResult->result ?? '';
                    if (preg_match('#(/storage/(?:svg|mermaid|plantuml|vegalite)/[a-f0-9-]+\.png|/storage/typst/[a-f0-9-]+\.pdf)#', $result, $m)) {
                        $url = $m[1];
                        $filePath = storage_path('app/public/' . str_replace('/storage/', '', $url));

                        MessageAttachment::create([
                            'id' => Str::uuid()->toString(),
                            'message_id' => $message->id,
                            'filename' => basename($url),
                            'original_name' => basename($url),
                            'mime_type' => str_ends_with($url, '.pdf') ? 'application/pdf' : 'image/png',
                            'size' => file_exists($filePath) ? filesize($filePath) : 0,
                            'url' => $url,
                            'uploaded_by_id' => $this->agent->id,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to save image attachments', ['error' => $e->getMessage()]);
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
            if ($channel?->type !== 'external' || $channel->external_provider !== 'telegram' || !$channel->external_id) {
                return;
            }

            $telegram = app(TelegramService::class);
            if (!$telegram->isConfigured()) {
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
            if (!$parentTask) {
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
            if (!$parentAgent) {
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

        if (!$message->reply_to_id) {
            return $prompt;
        }

        $parent = $message->replyTo()->with('author')->first();
        if (!$parent) {
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
}
