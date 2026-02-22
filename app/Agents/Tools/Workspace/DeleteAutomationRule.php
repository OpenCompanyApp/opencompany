<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListAutomationRule;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteAutomationRule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Delete an automation rule.';
    }

    public function handle(Request $request): string
    {
        try {
            $ruleId = $request['ruleId'] ?? null;
            if (!$ruleId) {
                return 'ruleId is required.';
            }

            $rule = ListAutomationRule::whereHas('template', fn($q) => $q->forWorkspace())->find($ruleId);
            if (!$rule) {
                return "Automation rule not found: {$ruleId}";
            }

            $name = $rule->name;
            $rule->delete();

            return "Automation rule deleted: {$name}";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ruleId' => $schema
                ->string()
                ->description('Automation rule UUID.')
                ->required(),
        ];
    }
}
