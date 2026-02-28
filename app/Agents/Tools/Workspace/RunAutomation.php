<?php

namespace App\Agents\Tools\Workspace;

use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RunAutomation implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Manually trigger an automation to run immediately in the background, regardless of its trigger schedule. Useful for testing or one-off runs.';
    }

    public function handle(Request $request): string
    {
        try {
            $automationId = $request['automationId'] ?? null;
            if (! $automationId) {
                return 'automationId is required.';
            }

            $automation = Automation::forWorkspace()->find($automationId);
            if (! $automation) {
                return "Automation not found: {$automationId}";
            }

            if (! $automation->is_active) {
                return "Automation '{$automation->name}' is disabled. Enable it first by updating isActive to true.";
            }

            RunAutomationJob::dispatch($automation);

            $type = $automation->execution_type ?? 'prompt';

            return "Triggered: {$automation->name} ({$type}). Running in the background.";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'automationId' => $schema
                ->string()
                ->description('Automation UUID.')
                ->required(),
        ];
    }
}
