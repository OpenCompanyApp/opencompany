<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteSchedule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete a scheduled automation.';
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

            $name = $schedule->name;
            $schedule->delete();

            return "Schedule deleted: {$name}";
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
