<?php

namespace App\Jobs;

use App\Agents\OpenCompanyAgent;
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
use App\Services\TelegramService;
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
    ) {}

    public function handle(): void
    {
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

        try {
            broadcast(new TaskUpdated($task, 'started'));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast task started', ['error' => $e->getMessage()]);
        }

        try {
            // Update agent status to working
            $this->agent->update(['status' => 'working']);

            // Start periodic typing indicator for Telegram channels
            $this->startTypingIndicator();

            // Step 1: Generate response
            $llmStep = $task->addStep('Generating response', 'action');
            $llmStep->start();

            // Create the agent and get a response (task context is in system prompt)
            $agentInstance = OpenCompanyAgent::for($this->agent, $this->channelId, $task->id);

            $response = $agentInstance->prompt($this->userMessage->content);

            $responseText = $response->text;

            if (empty($responseText)) {
                $responseText = "I processed your request but didn't generate a text response.";
            }

            $llmStep->complete();

            // Log tool usage as task steps
            $this->logToolSteps($task, $response);

            // Step 2: Deliver response
            $deliveryStep = $task->addStep('Response delivered', 'message');
            $deliveryStep->start();

            // Create the agent's response message
            $agentMessage = Message::create([
                'id' => Str::uuid()->toString(),
                'content' => $responseText,
                'channel_id' => $this->channelId,
                'author_id' => $this->agent->id,
                'timestamp' => now(),
            ]);

            // Save chart images from tool results as message attachments
            $this->saveChartAttachments($response, $agentMessage);

            // Update channel timestamp
            Channel::where('id', $this->channelId)
                ->update(['last_message_at' => now()]);

            // Update DM timestamp if applicable
            DirectMessage::where('channel_id', $this->channelId)
                ->update(['last_message_at' => now()]);

            // Broadcast agent's response (non-critical — message is already saved to DB)
            try {
                broadcast(new MessageSent($agentMessage));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast agent message', [
                    'error' => $broadcastError->getMessage(),
                    'channel' => $this->channelId,
                ]);
            }

            $deliveryStep->complete();

            // Complete the task
            $task->complete([
                'response' => Str::limit($responseText, 500),
                'tokens' => $response->usage?->totalTokens ?? null,
                'tool_calls_count' => $response->toolCalls->count(),
            ]);

            try {
                broadcast(new TaskUpdated($task, 'completed'));
            } catch (\Throwable $broadcastError) {
                Log::warning('Failed to broadcast task completed', ['error' => $broadcastError->getMessage()]);
            }

            Log::info('Agent responded', [
                'agent' => $this->agent->name,
                'channel' => $this->channelId,
                'task' => $task->id,
                'tokens' => $response->usage?->totalTokens ?? 'unknown',
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
            } else {
                $this->agent->update(['status' => 'idle']);
            }
        }
    }

    /**
     * Save chart images from tool call results as message attachments.
     */
    private function saveChartAttachments($response, Message $message): void
    {
        try {
            foreach ($response->toolResults as $toolResult) {
                if ($toolResult->name !== 'create_jpgraph_chart') {
                    continue;
                }

                $result = $toolResult->result ?? '';
                if (preg_match('#(/storage/charts/[a-f0-9-]+\.png)#', $result, $m)) {
                    $url = $m[1];
                    $filePath = storage_path('app/public/' . str_replace('/storage/', '', $url));

                    MessageAttachment::create([
                        'id' => Str::uuid()->toString(),
                        'message_id' => $message->id,
                        'filename' => basename($url),
                        'original_name' => basename($url),
                        'mime_type' => 'image/png',
                        'size' => file_exists($filePath) ? filesize($filePath) : 0,
                        'url' => $url,
                        'uploaded_by_id' => $this->agent->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to save chart attachments', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Log tool calls from the agent response as task steps.
     */
    private function logToolSteps(Task $task, $response): void
    {
        foreach ($response->steps as $step) {
            foreach ($step->toolCalls as $toolCall) {
                $toolStep = $task->addStep(
                    "Used tool: {$toolCall->name}",
                    'action',
                    [
                        'tool' => $toolCall->name,
                        'arguments' => $toolCall->arguments,
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
            if ($channel?->type !== 'external' || $channel?->external_provider !== 'telegram' || !$channel?->external_id) {
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
