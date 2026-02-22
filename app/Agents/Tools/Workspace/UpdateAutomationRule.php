<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListAutomationRule;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAutomationRule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update an existing automation rule (name, trigger, action, active status).';
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

            $updates = [];

            if (isset($request['name'])) {
                $updates['name'] = $request['name'];
            }
            if (isset($request['triggerType'])) {
                $updates['trigger_type'] = $request['triggerType'];
            }
            if (!empty($request['triggerConditions'])) {
                $decoded = json_decode($request['triggerConditions'], true);
                if (is_array($decoded)) {
                    $updates['trigger_conditions'] = $decoded;
                }
            }
            if (isset($request['actionType'])) {
                $updates['action_type'] = $request['actionType'];
            }
            if (!empty($request['actionConfig'])) {
                $decoded = json_decode($request['actionConfig'], true);
                if (is_array($decoded)) {
                    $updates['action_config'] = $decoded;
                }
            }
            if (isset($request['isActive'])) {
                $updates['is_active'] = filter_var($request['isActive'], FILTER_VALIDATE_BOOLEAN);
            }

            if (empty($updates)) {
                return 'No fields to update.';
            }

            $rule->update($updates);

            return "Automation rule updated: {$rule->name} (ID: {$rule->id})";
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
            'name' => $schema
                ->string()
                ->description('New rule name.'),
            'triggerType' => $schema
                ->string()
                ->description('Trigger type (e.g., "status_change", "new_item", "schedule").'),
            'triggerConditions' => $schema
                ->string()
                ->description('JSON object with trigger conditions.'),
            'actionType' => $schema
                ->string()
                ->description('Action type (e.g., "create_item", "notify", "assign_agent").'),
            'actionConfig' => $schema
                ->string()
                ->description('JSON object with action configuration.'),
            'isActive' => $schema
                ->string()
                ->description("'true' or 'false' to activate/deactivate the rule."),
        ];
    }
}
