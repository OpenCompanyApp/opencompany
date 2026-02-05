<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AgentResumeFromSleepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private User $agent,
    ) {}

    public function handle(): void
    {
        $this->agent->refresh();

        if ($this->agent->status !== 'sleeping') {
            return;
        }

        $this->agent->update([
            'sleeping_until' => null,
            'sleeping_reason' => null,
            'status' => 'idle',
        ]);

        Log::info('Agent resumed from sleep', [
            'agent' => $this->agent->name,
        ]);
    }
}
