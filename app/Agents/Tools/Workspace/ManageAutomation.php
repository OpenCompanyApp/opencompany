<?php

namespace App\Agents\Tools\Workspace;

use App\Models\ListAutomationRule;
use App\Models\ListTemplate;
use App\Models\ScheduledAutomation;
use App\Models\User;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ManageAutomation implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create and manage automation rules, list templates, and scheduled automations (cron jobs for agents).';
    }

    public function handle(Request $request): string
    {
        try {
            return match ($request['action']) {
                'create_rule' => $this->createRule($request),
                'update_rule' => $this->updateRule($request),
                'delete_rule' => $this->deleteRule($request),
                'create_template' => $this->createTemplate($request),
                'update_template' => $this->updateTemplate($request),
                'delete_template' => $this->deleteTemplate($request),
                'create_schedule' => $this->createSchedule($request),
                'update_schedule' => $this->updateSchedule($request),
                'delete_schedule' => $this->deleteSchedule($request),
                'list_schedules' => $this->listSchedules(),
                'enable_schedule' => $this->toggleSchedule($request, true),
                'disable_schedule' => $this->toggleSchedule($request, false),
                default => "Unknown action: {$request['action']}. Use: create_rule, update_rule, delete_rule, create_template, update_template, delete_template, create_schedule, update_schedule, delete_schedule, list_schedules, enable_schedule, disable_schedule",
            };
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    private function createRule(Request $request): string
    {
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
    }

    private function updateRule(Request $request): string
    {
        $ruleId = $request['ruleId'] ?? null;
        if (!$ruleId) {
            return 'ruleId is required.';
        }

        $rule = ListAutomationRule::find($ruleId);
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
    }

    private function deleteRule(Request $request): string
    {
        $ruleId = $request['ruleId'] ?? null;
        if (!$ruleId) {
            return 'ruleId is required.';
        }

        $rule = ListAutomationRule::find($ruleId);
        if (!$rule) {
            return "Automation rule not found: {$ruleId}";
        }

        $name = $rule->name;
        $rule->delete();

        return "Automation rule deleted: {$name}";
    }

    private function createTemplate(Request $request): string
    {
        $name = $request['name'] ?? null;
        if (!$name) {
            return 'name is required.';
        }

        $tags = [];
        if (!empty($request['tags'])) {
            $decoded = json_decode($request['tags'], true);
            if (is_array($decoded)) {
                $tags = $decoded;
            }
        }

        $template = ListTemplate::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'default_title' => $request['defaultTitle'] ?? null,
            'default_description' => $request['defaultDescription'] ?? null,
            'default_priority' => $request['defaultPriority'] ?? 'medium',
            'default_assignee_id' => $request['defaultAssigneeId'] ?? null,
            'estimated_cost' => $request['estimatedCost'] ?? null,
            'tags' => $tags,
            'created_by_id' => $this->agent->id,
            'is_active' => true,
        ]);

        return "Template created: {$template->name} (ID: {$template->id})";
    }

    private function updateTemplate(Request $request): string
    {
        $templateId = $request['templateId'] ?? null;
        if (!$templateId) {
            return 'templateId is required.';
        }

        $template = ListTemplate::find($templateId);
        if (!$template) {
            return "Template not found: {$templateId}";
        }

        $updates = [];

        if (isset($request['name'])) {
            $updates['name'] = $request['name'];
        }
        if (isset($request['defaultTitle'])) {
            $updates['default_title'] = $request['defaultTitle'];
        }
        if (isset($request['defaultPriority'])) {
            $updates['default_priority'] = $request['defaultPriority'];
        }
        if (isset($request['isActive'])) {
            $updates['is_active'] = filter_var($request['isActive'], FILTER_VALIDATE_BOOLEAN);
        }

        if (empty($updates)) {
            return 'No fields to update.';
        }

        $template->update($updates);

        return "Template updated: {$template->name} (ID: {$template->id})";
    }

    private function deleteTemplate(Request $request): string
    {
        $templateId = $request['templateId'] ?? null;
        if (!$templateId) {
            return 'templateId is required.';
        }

        $template = ListTemplate::find($templateId);
        if (!$template) {
            return "Template not found: {$templateId}";
        }

        $name = $template->name;
        $template->delete();

        return "Template deleted: {$name}";
    }

    // --- Scheduled Automations ---

    private function createSchedule(Request $request): string
    {
        $name = $request['name'] ?? null;
        $prompt = $request['prompt'] ?? null;
        $cronExpression = $request['cronExpression'] ?? null;

        if (!$name || !$prompt || !$cronExpression) {
            return 'Required: name, prompt, cronExpression.';
        }

        if (!CronExpression::isValidExpression($cronExpression)) {
            return "Invalid cron expression: {$cronExpression}. Use standard 5-field format, e.g. '0 9 * * 1-5' for weekdays at 9am.";
        }

        $agentId = $request['agentId'] ?? $this->agent->id;

        $schedule = ScheduledAutomation::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'description' => $request['description'] ?? null,
            'agent_id' => $agentId,
            'prompt' => $prompt,
            'cron_expression' => $cronExpression,
            'timezone' => $request['timezone'] ?? 'UTC',
            'channel_id' => $request['channelId'] ?? null,
            'created_by_id' => $this->agent->id,
            'is_active' => true,
        ]);

        $nextRun = $schedule->next_run_at?->format('Y-m-d H:i T');

        return "Schedule created: {$schedule->name} (ID: {$schedule->id}, cron: {$cronExpression}, next run: {$nextRun})";
    }

    private function updateSchedule(Request $request): string
    {
        $scheduleId = $request['scheduleId'] ?? null;
        if (!$scheduleId) {
            return 'scheduleId is required.';
        }

        $schedule = ScheduledAutomation::find($scheduleId);
        if (!$schedule) {
            return "Schedule not found: {$scheduleId}";
        }

        $updates = [];

        if (isset($request['name'])) {
            $updates['name'] = $request['name'];
        }
        if (isset($request['prompt'])) {
            $updates['prompt'] = $request['prompt'];
        }
        if (isset($request['cronExpression'])) {
            if (!CronExpression::isValidExpression($request['cronExpression'])) {
                return "Invalid cron expression: {$request['cronExpression']}";
            }
            $updates['cron_expression'] = $request['cronExpression'];
        }
        if (isset($request['timezone'])) {
            $updates['timezone'] = $request['timezone'];
        }
        if (isset($request['agentId'])) {
            $updates['agent_id'] = $request['agentId'];
        }
        if (isset($request['channelId'])) {
            $updates['channel_id'] = $request['channelId'];
        }

        if (empty($updates)) {
            return 'No fields to update.';
        }

        $schedule->update($updates);

        if (isset($updates['cron_expression']) || isset($updates['timezone'])) {
            $schedule->refreshNextRunAt();
        }

        return "Schedule updated: {$schedule->name} (ID: {$schedule->id})";
    }

    private function deleteSchedule(Request $request): string
    {
        $scheduleId = $request['scheduleId'] ?? null;
        if (!$scheduleId) {
            return 'scheduleId is required.';
        }

        $schedule = ScheduledAutomation::find($scheduleId);
        if (!$schedule) {
            return "Schedule not found: {$scheduleId}";
        }

        $name = $schedule->name;
        $schedule->delete();

        return "Schedule deleted: {$name}";
    }

    private function listSchedules(): string
    {
        $schedules = ScheduledAutomation::with('agent')
            ->orderBy('name')
            ->get();

        if ($schedules->isEmpty()) {
            return 'No scheduled automations found.';
        }

        return $schedules->map(function ($s) {
            $status = $s->is_active ? 'active' : 'disabled';
            $nextRun = $s->next_run_at?->format('Y-m-d H:i T') ?? 'N/A';
            $agentName = $s->agent?->name ?? 'Unknown';

            return "- {$s->name} (ID: {$s->id}, agent: {$agentName}, {$status}, runs: {$s->run_count}, next: {$nextRun})";
        })->join("\n");
    }

    private function toggleSchedule(Request $request, bool $active): string
    {
        $scheduleId = $request['scheduleId'] ?? null;
        if (!$scheduleId) {
            return 'scheduleId is required.';
        }

        $schedule = ScheduledAutomation::find($scheduleId);
        if (!$schedule) {
            return "Schedule not found: {$scheduleId}";
        }

        $schedule->update([
            'is_active' => $active,
            'consecutive_failures' => $active ? 0 : $schedule->consecutive_failures,
        ]);

        if ($active) {
            $schedule->refreshNextRunAt();
        }

        $state = $active ? 'enabled' : 'disabled';

        return "Schedule {$state}: {$schedule->name}";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema
                ->string()
                ->description("Action: 'create_rule', 'update_rule', 'delete_rule', 'create_template', 'update_template', 'delete_template', 'create_schedule', 'update_schedule', 'delete_schedule', 'list_schedules', 'enable_schedule', 'disable_schedule'")
                ->required(),
            'name' => $schema
                ->string()
                ->description('Name for rule or template. Required for create_rule and create_template.'),
            'ruleId' => $schema
                ->string()
                ->description('Automation rule UUID. Required for update_rule and delete_rule.'),
            'templateId' => $schema
                ->string()
                ->description('Template UUID. Required for update_template and delete_template. Optional for create_rule (links rule to template).'),
            'triggerType' => $schema
                ->string()
                ->description('Trigger type for rules (e.g., "status_change", "new_item", "schedule"). Required for create_rule.'),
            'triggerConditions' => $schema
                ->string()
                ->description('JSON object with trigger conditions, e.g. {"from_status":"todo","to_status":"done"}'),
            'actionType' => $schema
                ->string()
                ->description('Action type for rules (e.g., "create_item", "notify", "assign_agent"). Required for create_rule.'),
            'actionConfig' => $schema
                ->string()
                ->description('JSON object with action configuration, e.g. {"channel_id":"...","message":"..."}'),
            'isActive' => $schema
                ->string()
                ->description("'true' or 'false' to activate/deactivate a rule or template."),
            'defaultTitle' => $schema
                ->string()
                ->description('Default title for template items.'),
            'defaultDescription' => $schema
                ->string()
                ->description('Default description for template items.'),
            'defaultPriority' => $schema
                ->string()
                ->description("Default priority for template items: 'low', 'medium', 'high', 'urgent'."),
            'defaultAssigneeId' => $schema
                ->string()
                ->description('Default assignee UUID for template items.'),
            'estimatedCost' => $schema
                ->string()
                ->description('Estimated cost for template items (decimal).'),
            'tags' => $schema
                ->string()
                ->description('JSON array of tags for template, e.g. ["support","bug"].'),
            'scheduleId' => $schema
                ->string()
                ->description('Schedule UUID. Required for update_schedule, delete_schedule, enable_schedule, disable_schedule.'),
            'prompt' => $schema
                ->string()
                ->description('The prompt to send to the agent on each scheduled run. Required for create_schedule.'),
            'cronExpression' => $schema
                ->string()
                ->description("Standard 5-field cron expression. E.g. '0 9 * * 1-5' for weekdays at 9am, '*/30 * * * *' for every 30 minutes. Required for create_schedule."),
            'agentId' => $schema
                ->string()
                ->description('Agent UUID to assign the schedule to. Defaults to self for create_schedule.'),
            'channelId' => $schema
                ->string()
                ->description('Channel UUID where the agent posts its response. Optional.'),
            'timezone' => $schema
                ->string()
                ->description("Timezone for the schedule, e.g. 'America/New_York'. Defaults to 'UTC'."),
        ];
    }
}
