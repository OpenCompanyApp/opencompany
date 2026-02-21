<?php

namespace App\Jobs;

use App\Models\ConversationSummary;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\MemoryFlushService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompactConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;
    /** @var array<int, int> */
    public array $backoff = [15];

    public function __construct(
        private string $channelId,
        private User $agent,
        private ?string $telegramChatId = null,
    ) {}

    public function handle(ConversationCompactionService $compactor, MemoryFlushService $flusher): void
    {
        // Set workspace context from agent
        if ($this->agent->workspace_id) {
            $workspace = Workspace::find($this->agent->workspace_id);
            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }

        // Ensure memory flush before compaction (race condition safety net:
        // AgentRespondJob dispatches this job before its own flush runs)
        try {
            $summary = ConversationSummary::where('channel_id', $this->channelId)
                ->where('agent_id', $this->agent->id)
                ->first();
            $maxFlushes = config('memory.memory_flush.max_flushes_per_cycle', 1);

            if (! $summary || $summary->flush_count < $maxFlushes) {
                $flusher->flush($this->channelId, $this->agent);
            }
        } catch (\Throwable $e) {
            Log::warning('Pre-compaction flush failed', [
                'channel_id' => $this->channelId,
                'agent' => $this->agent->name,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $result = $compactor->compact($this->channelId, $this->agent);

            if ($this->telegramChatId) {
                $this->sendTelegramResult($result);
            }
        } catch (\Throwable $e) {
            Log::error('CompactConversationJob failed', [
                'channel_id' => $this->channelId,
                'agent' => $this->agent->name,
                'error' => $e->getMessage(),
            ]);

            if ($this->telegramChatId) {
                $this->sendTelegramError($e);
            }

            throw $e;
        }
    }

    private function sendTelegramResult(?ConversationSummary $summary): void
    {
        try {
            $telegram = app(TelegramService::class);

            if (!$summary) {
                $telegram->sendMessage($this->telegramChatId, 'Nothing to compact (fewer than 5 messages).');
                return;
            }

            $telegram->sendMessage($this->telegramChatId,
                "<b>Compaction complete</b>\n\n"
                . "Messages summarized: <b>{$summary->messages_summarized}</b>\n"
                . "Tokens before: {$summary->tokens_before}\n"
                . "Tokens after: {$summary->tokens_after}\n"
                . "Compaction #: {$summary->compaction_count}\n\n"
                . "<i>" . Str::limit($summary->summary, 200) . "</i>"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send compaction result to Telegram', ['error' => $e->getMessage()]);
        }
    }

    private function sendTelegramError(\Throwable $error): void
    {
        try {
            $telegram = app(TelegramService::class);
            $telegram->sendMessage($this->telegramChatId, 'Compaction failed: ' . Str::limit($error->getMessage(), 200));
        } catch (\Throwable $e) {
            Log::warning('Failed to send compaction error to Telegram', ['error' => $e->getMessage()]);
        }
    }
}
