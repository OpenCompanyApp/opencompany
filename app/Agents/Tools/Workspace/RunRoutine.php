<?php

namespace App\Agents\Tools\Workspace;

use App\Jobs\RunBuiltinRoutineJob;
use App\Jobs\RunRoutineJob;
use App\Models\Routine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Agent tool `run_routine`: manually dispatches an active routine of the
 * current workspace. A manual run starts its own causality chain and gets no
 * extra permissions; the queued executor does the work.
 */
class RunRoutine implements Tool
{
    public function description(): string
    {
        return 'Manually trigger a routine to run immediately in the background, regardless of its trigger schedule. Useful for testing or one-off runs.';
    }

    public function handle(Request $request): string
    {
        try {
            $routineId = $request['routineId'] ?? null;
            if (! $routineId) {
                return 'routineId is required.';
            }

            $routine = Routine::forWorkspace()->find($routineId);
            if (! $routine) {
                return "Routine not found: {$routineId}";
            }

            if (! $routine->is_active) {
                return "Routine '{$routine->name}' is disabled. Enable it first by updating isActive to true.";
            }

            if ($routine->isBuiltin()) {
                RunBuiltinRoutineJob::dispatch($routine);
            } else {
                RunRoutineJob::dispatch($routine);
            }

            return "Triggered: {$routine->name} ({$routine->kind}). Running in the background.";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'routineId' => $schema
                ->string()
                ->description('Routine UUID.')
                ->required(),
        ];
    }
}
