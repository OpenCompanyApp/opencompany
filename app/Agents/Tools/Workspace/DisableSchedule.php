<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DisableSchedule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Disable a scheduled automation.';
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

            $schedule->update([
                'is_active' => false,
            ]);

            return "Schedule disabled: {$schedule->name}";
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
