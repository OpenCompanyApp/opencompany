<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListAutomationRule;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateAutomationRule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a new automation rule with trigger and action configuration.';
    }

    public function handle(Request $request): string
    {
        try {
            $name = $request['name'] ?? null;
            $triggerType = $request['triggerType'] ?? null;
            $actionType = $request['actionType'] ?? null;

            if (!$name || !$triggerType || !$actionType) {
                return 'Required: name, triggerType, actionType.';
            }

            $triggerConditions = [];
            if (!empty($request['triggerConditions'])) {
                $decoded = json_decode($request['triggerConditions'], true);
                if (is_array($decoded)) {
                    $triggerConditions = $decoded;
                }
            }

            $actionConfig = [];
            if (!empty($request['actionConfig'])) {
                $decoded = json_decode($request['actionConfig'], true);
                if (is_array($decoded)) {
                    $actionConfig = $decoded;
                }
            }

            $rule = ListAutomationRule::create([
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'trigger_type' => $triggerType,
                'trigger_conditions' => $triggerConditions,
                'action_type' => $actionType,
                'action_config' => $actionConfig,
                'list_template_id' => $request['templateId'] ?? null,
                'is_active' => true,
                'trigger_count' => 0,
                'created_by_id' => $this->agent->id,
            ]);

            return "Automation rule created: {$rule->name} (ID: {$rule->id}, trigger: {$triggerType}, action: {$actionType})";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema
                ->string()
                ->description('Rule name.')
                ->required(),
            'triggerType' => $schema
                ->string()
                ->description('Trigger type (e.g., "status_change", "new_item", "schedule").')
                ->required(),
            'actionType' => $schema
                ->string()
                ->description('Action type (e.g., "create_item", "notify", "assign_agent").')
                ->required(),
            'triggerConditions' => $schema
                ->string()
                ->description('JSON object with trigger conditions, e.g. {"from_status":"todo","to_status":"done"}'),
            'actionConfig' => $schema
                ->string()
                ->description('JSON object with action configuration, e.g. {"channel_id":"...","message":"..."}'),
            'templateId' => $schema
                ->string()
                ->description('Template UUID to link the rule to.'),
        ];
    }
}
