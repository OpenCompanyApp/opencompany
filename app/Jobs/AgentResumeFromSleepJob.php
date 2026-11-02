<?php

namespace App\Jobs;

use App\Events\AgentStatusUpdated;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Wakes a sleeping agent (status back to idle) and broadcasts the change.
 *
 * Workspace: runs with the agent's workspace bound via withWorkspaceBinding()
 * and restores the caller's binding afterwards.
 */
class AgentResumeFromSleepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetsWorkspaceContext;

    public int $tries = 1;

    public function __construct(
        private User $agent,
    ) {}

    public function handle(): void
    {
        // Bound for the job only; the caller's binding is restored after.
        $workspace = $this->agent->workspace_id ? Workspace::find($this->agent->workspace_id) : null;

        $this->withWorkspaceBinding($workspace, fn () => $this->resume());
    }

    private function resume(): void
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

        try {
            broadcast(new AgentStatusUpdated($this->agent));
        } catch (\Throwable $e) {
            Log::warning('Failed to broadcast agent status after sleep resume', ['error' => $e->getMessage()]);
        }

        Log::info('Agent resumed from sleep', [
            'agent' => $this->agent->name,
        ]);
    }
}
