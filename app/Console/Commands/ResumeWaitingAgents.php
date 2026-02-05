<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            $agent->update([
                'sleeping_until' => null,
                'sleeping_reason' => null,
                'status' => 'idle',
            ]);

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
