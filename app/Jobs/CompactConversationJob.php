<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Memory\ConversationCompactionService;
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
    public int $timeout = 120;
    public array $backoff = [15];

    public function __construct(
        private string $channelId,
        private User $agent,
    ) {}

    public function handle(ConversationCompactionService $compactor): void
    {
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
