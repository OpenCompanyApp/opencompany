<?php

namespace App\Jobs;

use App\Models\ConversationSummary;
use App\Models\User;
use App\Services\Memory\ConversationCompactionService;
use App\Services\Memory\MemoryFlushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
    ) {}

    public function handle(ConversationCompactionService $compactor, MemoryFlushService $flusher): void
    {
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
            $compactor->compact($this->channelId, $this->agent);
        } catch (\Throwable $e) {
            Log::error('CompactConversationJob failed', [
                'channel_id' => $this->channelId,
                'agent' => $this->agent->name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
