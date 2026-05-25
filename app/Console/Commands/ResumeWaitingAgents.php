<?php

namespace App\Console\Commands;

use App\Events\AgentStatusUpdated;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduler command that wakes agents after a wait/sleep period expires.
 *
 * Wait tools persist sleeping state on the agent record; this command converts
 * elapsed sleepers back to idle and broadcasts status changes to clients.
 */
class ResumeWaitingAgents extends Command
{
    protected $signature = 'agent:resume-waiting';

    protected $description = 'Resume agents whose sleep duration has elapsed';

    public function handle(): int
    {
        $agents = User::where('type', 'agent')
            ->where('status', 'sleeping')
            ->where('sleeping_until', '<=', now())
            ->get();

        foreach ($agents as $agent) {
            // Clear both timing and reason together so the UI never displays an
            // idle agent with stale sleep metadata.
            $agent->update([
                'sleeping_until' => null,
                'sleeping_reason' => null,
                'status' => 'idle',
            ]);

            broadcast(new AgentStatusUpdated($agent));

            Log::info('Agent resumed from sleep by scheduler', [
                'agent' => $agent->name,
            ]);
        }

        if ($agents->isNotEmpty()) {
            $this->info("Resumed {$agents->count()} sleeping agent(s).");
        }

        return Command::SUCCESS;
    }
}
