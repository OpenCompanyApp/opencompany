<?php

namespace App\Jobs;

use App\Domain\Events\Causality;
use App\Domain\Routines\Builtins\BuiltinRoutineRegistry;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\Routine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue transport for one built-in routine run (kind `builtin`).
 *
 * Built-ins are code-defined plumbing with no agent and no LLM, so they do
 * not create a Task/AgentRunRecord pair and do not take the per-agent
 * execution lock. Their run history is the routine's counters and last
 * result plus the `routine.triggered` event that started the run; the
 * causality chain is recorded in the last result.
 *
 * One attempt: built-ins are idempotent per slot and the next tick runs them
 * again, so a failure is recorded rather than retried.
 */
class RunBuiltinRoutineJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SetsWorkspaceContext;

    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 300;

    public function __construct(
        private Routine $routine,
        private ?Causality $causality = null,
    ) {}

    public function uniqueId(): string
    {
        return 'routine_builtin_'.$this->routine->id;
    }

    public function handle(BuiltinRoutineRegistry $registry): void
    {
        $routine = Routine::query()->whereKey($this->routine->id)->first();
        if ($routine === null || ! $routine->isBuiltin()) {
            return;
        }

        $definition = $registry->find($routine->built_in_key);
        $this->setWorkspaceContext($routine->workspace_id);

        try {
            if ($definition === null) {
                $routine->recordFailure("Unknown built-in routine '{$routine->built_in_key}'.", ['retryable' => false]);

                return;
            }

            $summary = $definition->run($routine);
            $routine->recordSuccess([
                ...$summary,
                ...$this->causalityDetails(),
                'completed_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Built-in routine failed', [
                'routine' => $routine->id,
                'built_in_key' => $routine->built_in_key,
                'error' => $exception->getMessage(),
            ]);
            $routine->recordFailure($exception->getMessage(), $this->causalityDetails());
        } finally {
            $this->clearWorkspaceContext();
        }
    }

    /** @return array<string, mixed> */
    private function causalityDetails(): array
    {
        return $this->causality === null ? [] : [
            'causality_id' => $this->causality->id,
            'causality_depth' => $this->causality->depth,
            'trigger_event_id' => $this->causality->parentEventId,
        ];
    }
}
