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
                $updates['trigger_conditions'] = $request['triggerConditions'];
            }
            if (isset($request['actionType'])) {
                $updates['action_type'] = $request['actionType'];
            }
            if (!empty($request['actionConfig'])) {
                $updates['action_config'] = $request['actionConfig'];
            }
            if (isset($request['isActive'])) {
                $updates['is_active'] = $request['isActive'];
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
                ->object()
                ->description('Trigger conditions.'),
            'actionType' => $schema
                ->string()
                ->description('Action type (e.g., "create_item", "notify", "assign_agent").'),
            'actionConfig' => $schema
                ->object()
                ->description('Action configuration.'),
            'isActive' => $schema
                ->boolean()
                ->description('Whether the rule should be active.'),
        ];
    }
}
