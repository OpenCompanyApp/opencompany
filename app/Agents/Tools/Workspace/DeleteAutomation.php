<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteAutomation implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete an automation and its schedule permanently.';
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

            $name = $automation->name;
            $automation->delete();

            return "Automation deleted: {$name}";
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
