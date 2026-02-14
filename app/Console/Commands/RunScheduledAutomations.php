<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledAutomationJob;
use App\Models\ScheduledAutomation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledAutomations extends Command
{
    protected $signature = 'automation:run-scheduled';

    protected $description = 'Evaluate and dispatch due scheduled automations';

    public function handle(): int
    {
        $due = ScheduledAutomation::where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($due as $automation) {
            // Immediately advance next_run_at to prevent duplicate dispatch
            $automation->update([
                'next_run_at' => $automation->computeNextRunAt(),
            ]);

            RunScheduledAutomationJob::dispatch($automation);

            Log::info('Scheduled automation dispatched', [
                'automation' => $automation->name,
                'agent' => $automation->agent_id,
            ]);
        }

        if ($due->isNotEmpty()) {
            $this->info("Dispatched {$due->count()} scheduled automation(s).");
        }

        return Command::SUCCESS;
    }
}
