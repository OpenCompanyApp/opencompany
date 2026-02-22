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
                return 'No automation rules found.';
            }

            $lines = ["Automation Rules ({$rules->count()}):"];
            foreach ($rules as $rule) {
                $active = $rule->is_active ? 'active' : 'inactive';
                $template = $rule->template ? " (template: {$rule->template->name})" : '';
                $lines[] = "- {$rule->name} (ID: {$rule->id}) — trigger: {$rule->trigger_type}, action: {$rule->action_type}, {$active}{$template}";
            }

            return implode("\n", $lines);
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