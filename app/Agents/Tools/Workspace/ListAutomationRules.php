<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListAutomationRule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListAutomationRules implements Tool
{
    public function description(): string
    {
        return 'List all automation rules in the workspace with their trigger type, action type, and status.';
    }

    public function handle(Request $request): string
    {
        try {
            $rules = ListAutomationRule::with('template', 'createdBy')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($rules->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($rules->map(fn ($rule) => array_filter([
                'id' => $rule->id,
                'name' => $rule->name,
                'triggerType' => $rule->trigger_type,
                'actionType' => $rule->action_type,
                'isActive' => $rule->is_active,
                'template' => $rule->template?->name,
            ]))->values()->toArray(), JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}