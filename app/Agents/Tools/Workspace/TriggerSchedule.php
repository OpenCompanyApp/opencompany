<?php

namespace App\Agents\Tools\Workspace;

use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class TriggerSchedule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Manually trigger a scheduled automation to run immediately in the background.';
    }

    public function handle(Request $request): string
    {
        try {
            $scheduleId = $request['scheduleId'] ?? null;
            if (!$scheduleId) {
                return 'scheduleId is required.';
            }

            $schedule = Automation::forWorkspace()->find($scheduleId);
            if (!$schedule) {
                return "Schedule not found: {$scheduleId}";
            }

            if (!$schedule->is_active) {
                return "Schedule '{$schedule->name}' is disabled. Enable it first.";
            }

            RunAutomationJob::dispatch($schedule);

            return "Triggered: {$schedule->name}. Running in the background.";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scheduleId' => $schema
                ->string()
                ->description('Schedule UUID.')
                ->required(),
        ];
    }
}
