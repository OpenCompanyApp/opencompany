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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AgentCommunicationService;
use App\Services\AgentDocumentService;
use App\Services\Memory\ModelContextRegistry;
use App\Services\TelegramService;
use Laravel\Ai\Responses\Data\FinishReason;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentRespondJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [10];

    private ?string $telegramChatId = null;

    public function __construct(
        private Message $userMessage,
        private User $agent,
        private string $channelId,
        private ?string $taskId = null,
    ) {}

    public function handle(): void
    {
        $task = null;

        // Resume an existing task (delegation callback re-invoking parent)
        if ($this->taskId) {
            $task = Task::find($this->taskId);
            if ($task) {
                $task->resume();
            }
        }

        if (!$task) {
            // Check for a pending delegation subtask for this agent on this channel
            $task = Task::where('agent_id', $this->agent->id)
                ->where('channel_id', $this->channelId)
                ->where('source', Task::SOURCE_AGENT_DELEGATION)
                ->where('status', Task::STATUS_PENDING)
                ->latest('created_at')
                ->first();

            if ($task) {
                $task->start();
            } else {
                // Create a task to track this chat interaction
                $task = Task::create([
                    'id' => Str::uuid()->toString(),
                    'title' => Str::limit($this->userMessage->content, 80),
                    'description' => $this->userMessage->content,
                    'type' => Task::TYPE_CUSTOM,
                    'status' => Task::STATUS_ACTIVE,
                    'priority' => Task::PRIORITY_NORMAL,
                    'source' => Task::SOURCE_CHAT,
                    'agent_id' => $this->agent->id,
                    'requester_id' => $this->userMessage->author_id,
                    'channel_id' => $this->channelId,
                    'started_at' => now(),
                ]);
            }
        }

        try {
            broadcast(new TaskUpdated($task, 'started'));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast task started', ['error' => $e->getMessage()]);
        }

        try {
            // Update agent status to working
            $this->agent->update(['status' => 'working']);
            broadcast(new AgentStatusUpdated($this->agent));

            // Start periodic typing indicator for Telegram channels
            $this->startTypingIndicator();

            // Step 1: Generate response
            $llmStep = $task->addStep('Generating response', 'action');
            $llmStep->start();

            // Create the agent and get a response (task context is in system prompt)
            $agentInstance = OpenCompanyAgent::for($this->agent, $this->channelId, $task->id);

            // Capture LLM context before prompting (for observability)
            try {
                $toolRegistry = app(\App\Agents\Tools\ToolRegistry::class);
                $task->update([
                    'context' => [
                        'system_prompt' => $agentInstance->instructions(),
                        'messages' => collect($agentInstance->messages())
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

            // Log tool usage as task steps
            $this->logToolSteps($task, $response);

            // Hold response if agent delegated work — wait for all results
            $this->agent->refresh();
            if (!empty($this->agent->awaiting_delegation_ids)) {
                $task->addStep('Awaiting delegation results', 'action');

                Log::info('Agent holding response while awaiting delegations', [
                    'agent' => $this->agent->name,
                    'task' => $task->id,
                    'awaiting' => $this->agent->awaiting_delegation_ids,
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

            // Complete the task
            $task->complete([
                'response' => $responseText,
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'cache_read_tokens' => $response->usage->cacheReadInputTokens,
                'cache_write_tokens' => $response->usage->cacheWriteInputTokens,
                'tool_calls_count' => collect($response->steps)->sum(fn ($step) => count($step->toolCalls)),
            ]);

            // Compute token breakdown from actual usage
            try {
                $promptTokens = $response->usage->promptTokens;
                $completionTokens = $response->usage->completionTokens;
                $contextWindow = $task->context['context_window'] ?? 128_000;
                $outputReserve = (int) config('memory.compaction.output_reserve', 4_096);

                // Estimate system vs message split from char lengths
                $systemChars = mb_strlen($task->context['system_prompt'] ?? '');
                $messageChars = array_sum(array_map(
                    fn ($m) => mb_strlen($m['content'] ?? ''),
                    $task->context['messages'] ?? [],
                ));
                $totalChars = $systemChars + $messageChars;
                $systemRatio = $totalChars > 0 ? $systemChars / $totalChars : 0.5;

                $systemTokens = (int) round($promptTokens * $systemRatio);
                $messageTokens = $promptTokens - $systemTokens;

                // Compaction math using actual tokens
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
                    'finish_reason' => $lastStep?->finishReason?->value ?? 'unknown',
                ];
                unset($context['prompt_sections'], $context['context_window']);
                $task->update(['context' => $context]);
            } catch (\Throwable $e) {
                Log::warning('Failed to compute token breakdown', ['error' => $e->getMessage()]);
            }

            // If this was a delegated task, send result back to the delegating agent
            if ($task->parent_task_id && $task->source === Task::SOURCE_AGENT_DELEGATION) {
                $this->handleDelegationCallback($task, $responseText);
            }

            try {
                broadcast(new TaskUpdated($task, 'completed'));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast task completed', ['error' => $broadcastError->getMessage()]);
            }

            Log::info('Agent responded', [
                'agent' => $this->agent->name,
                'channel' => $this->channelId,
                'task' => $task->id,
                'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ]);
        } catch (\Throwable $e) {
            Log::error('Agent response failed', [
                'agent' => $this->agent->name,
                'channel' => $this->channelId,
                'task' => $task->id,
                'error' => $e->getMessage(),
            ]);

            $task->addStep("Error: {$e->getMessage()}", 'action');
            $task->fail($e->getMessage());

            // If this was a delegated task, send error back to the delegating agent
            if ($task->parent_task_id && $task->source === Task::SOURCE_AGENT_DELEGATION) {
                $this->handleDelegationCallback($task, "Error: {$this->agent->name} failed to complete the task: {$e->getMessage()}");
            }

            try {
                broadcast(new TaskUpdated($task, 'failed'));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast task failed', ['error' => $broadcastError->getMessage()]);
            }

            // Send error message as agent response so user isn't left hanging
            $this->sendErrorMessage($e);
        } finally {
            $this->stopTypingIndicator();

            // Refresh to pick up changes made by tools during execution
            $this->agent->refresh();

            if ($this->agent->sleeping_until) {
                $this->agent->update(['status' => 'sleeping']);
            } elseif ($this->agent->awaiting_approval_id) {
                $this->agent->update(['status' => 'awaiting_approval']);
            } elseif (!empty($this->agent->awaiting_delegation_ids)) {
                $this->agent->update(['status' => 'awaiting_delegation']);
            } else {
                $this->agent->update(['status' => 'idle']);
            }
            broadcast(new AgentStatusUpdated($this->agent));
        }
    }

    /**
     * Save generated images from tool call results as message attachments.
     */
    private function saveImageAttachments($response, Message $message): void
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
     * Log tool calls from the agent response as task steps.
     */
    private function logToolSteps(Task $task, $response): void
    {
        foreach ($response->steps as $step) {
            // Build a lookup of tool results keyed by tool call ID
            $resultsByCallId = [];
            foreach ($step->toolResults as $toolResult) {
                $resultsByCallId[$toolResult->id] = $toolResult->result;
            }

            foreach ($step->toolCalls as $toolCall) {
                $result = $resultsByCallId[$toolCall->id] ?? null;

                // Truncate large string results to prevent DB bloat
                if (is_string($result) && strlen($result) > 2000) {
                    $result = mb_strcut($result, 0, 2000, 'UTF-8') . '... [truncated]';
                }

                // Sanitize to valid UTF-8 to prevent JSON encoding failures
                if (is_string($result)) {
                    $result = mb_convert_encoding($result, 'UTF-8', 'UTF-8');
                }

                $toolStep = $task->addStep(
                    "Used tool: {$toolCall->name}",
                    'action',
                    [
                        'tool' => $toolCall->name,
                        'arguments' => $toolCall->arguments,
                        'result' => $result,
                    ]
                );
                $toolStep->start();
                $toolStep->complete();
            }
        }
    }

    /**
     * Start a periodic typing indicator for Telegram channels.
     * Resends every 4 seconds using SIGALRM until stopped.
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

            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGALRM, function () use ($telegram) {
                    if ($this->telegramChatId) {
                        try {
                            $telegram->sendChatAction($this->telegramChatId);
                        } catch (\Throwable) {
                            // Non-critical
                        }
                        pcntl_alarm(4);
                    }
                });
                pcntl_alarm(4);
            }
        } catch (\Throwable) {
            // Non-critical
        }
    }

    /**
     * Stop the periodic typing indicator.
     */
    private function stopTypingIndicator(): void
    {
        $this->telegramChatId = null;
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0);
        }
    }

    /**
     * Handle delegation callback: send result back to the delegating agent.
     */
    private function handleDelegationCallback(Task $subtask, string $resultText): void
    {
        try {
            $parentTask = $subtask->parentTask;
            if (!$parentTask) {
                return;
            }

            $parentAgent = User::find($parentTask->agent_id);
            if (!$parentAgent) {
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
                    ->where('source', Task::SOURCE_AGENT_DELEGATION)
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
