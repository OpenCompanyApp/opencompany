<?php

namespace App\Console\Commands;

use App\Domain\Automations\Application\ResolveAutomationRun;
use App\Jobs\RunAutomationJob;
use App\Models\AgentRunRecord;
use App\Models\Automation;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Claims due schedules and creates a durable logical run before enqueueing.
 *
 * The command scans all workspaces by design. Each claimed row is locked, its
 * Task/AgentRunRecord is created with explicit workspace ownership, and only
 * then is the single default-queue adapter dispatched. Reconciliation can
 * recover a process crash between the durable claim and physical enqueue.
 */
class RunDueAutomations extends Command
{
    protected $signature = 'automation:run-due';

    protected $description = 'Evaluate and dispatch due automations';

    public function handle(): int
    {
        $dueIds = Automation::where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->pluck('id');

        $dispatched = 0;

        foreach ($dueIds as $automationId) {
            try {
                /** @var array{automation: Automation, dispatch_key: string}|null $claim */
                $claim = DB::transaction(function () use ($automationId): ?array {
                    $automation = Automation::query()
                        ->whereKey($automationId)
                        ->where('is_active', true)
                        ->where('next_run_at', '<=', now())
                        ->lockForUpdate()
                        ->first();

                    if (! $automation) {
                        return null;
                    }

                    $dispatchKey = (string) Str::uuid();
                    app()->instance('currentWorkspace', Workspace::query()->findOrFail($automation->workspace_id));

                    try {
                        // Commit the typed run/task before physical dispatch.
                        // If the command dies in the following narrow window,
                        // agent-runs:reconcile can redispatch this exact key.
                        app(ResolveAutomationRun::class)->handle(
                            $automation,
                            $dispatchKey,
                            $automation->isScript()
                                ? AgentRunRecord::KIND_SCRIPT_AUTOMATION
                                : AgentRunRecord::KIND_PROMPT_AUTOMATION,
                            2,
                        );
                    } finally {
                        app()->forgetInstance('currentWorkspace');
                    }

                    // Claim the schedule while the row is locked. Another
                    // scheduler observes the advanced timestamp and skips it.
                    $automation->update([
                        'next_run_at' => $automation->computeNextRunAt(),
                    ]);

                    return [
                        'automation' => $automation,
                        'dispatch_key' => $dispatchKey,
                    ];
                });
            } catch (\DomainException $exception) {
                Automation::query()->find($automationId)?->recordFailure($exception->getMessage());
                Log::error('Due automation rejected before dispatch', [
                    'automation' => $automationId,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if (! $claim) {
                continue;
            }

            RunAutomationJob::dispatch($claim['automation'], $claim['dispatch_key']);
            $dispatched++;

            Log::info('Automation dispatched', [
                'automation' => $claim['automation']->name,
                'agent' => $claim['automation']->agent_id,
                'dispatch_key' => $claim['dispatch_key'],
            ]);
        }

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} automation(s).");
        }

        return Command::SUCCESS;
    }
}
