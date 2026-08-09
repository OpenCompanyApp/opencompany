<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\User;
use App\Services\QuickJsSandboxService;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAutomation implements Tool
{
    public function __construct(
        private User $agent,
        private QuickJsSandboxService $sandbox,
    ) {}

    public function description(): string
    {
        return "Update an automation's configuration. Setting isActive to true resets the failure counter.";
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

            $updates = [];

            if (isset($request['name'])) {
                $updates['name'] = $request['name'];
            }
            if (isset($request['description'])) {
                $updates['description'] = $request['description'];
            }
            if (isset($request['executionType'])) {
                if (! in_array($request['executionType'], ['prompt', 'script'])) {
                    return "executionType must be 'prompt' or 'script'.";
                }
                $updates['execution_type'] = $request['executionType'];
            }
            if (isset($request['prompt'])) {
                $updates['prompt'] = $request['prompt'];
            }
            if (isset($request['script'])) {
                $updates['script'] = $request['script'];
            }

            $targetType = $updates['execution_type'] ?? $automation->execution_type;
            if ($targetType === 'script'
                && (isset($request['script']) || $automation->script_runtime !== config('code.runtime'))) {
                $script = (string) ($updates['script'] ?? $automation->script ?? '');
                if (trim($script) === '') {
                    return 'A JavaScript body is required before enabling a script automation.';
                }

                $validation = $this->sandbox->execute(
                    code: $script,
                    profile: 'automation',
                    validateOnly: true,
                    sourceName: 'automation-code.js',
                );
                if (! $validation->succeeded()) {
                    $error = $validation->error ?? [];

                    return 'JavaScript validation failed ['.($error['type'] ?? 'syntax_error').']'
                        .(isset($error['line']) ? ' at line '.$error['line'] : '')
                        .': '.($error['message'] ?? 'Invalid source.');
                }
                $updates['script_runtime'] = config('code.runtime');
            } elseif ($targetType === 'prompt') {
                $updates['script_runtime'] = null;
            }
            if (isset($request['cronExpression'])) {
                if (! CronExpression::isValidExpression($request['cronExpression'])) {
                    return "Invalid cron expression: {$request['cronExpression']}";
                }
                $updates['cron_expression'] = $request['cronExpression'];
            }
            if (isset($request['timezone'])) {
                $updates['timezone'] = $request['timezone'];
            }
            if (isset($request['agentId'])) {
                $targetAgent = User::where('type', 'agent')
                    ->where('workspace_id', $this->agent->workspace_id)
                    ->find($request['agentId']);
                if (! $targetAgent) {
                    return 'Error: Agent not found in this workspace.';
                }
                $updates['agent_id'] = $request['agentId'];
            }
            if (isset($request['channelId'])) {
                $channel = Channel::forWorkspace()->find($request['channelId']);
                if (! $channel) {
                    return 'Error: Channel not found in this workspace.';
                }
                $updates['channel_id'] = $request['channelId'];
            }
            if (isset($request['isActive'])) {
                $updates['is_active'] = (bool) $request['isActive'];
                if ($updates['is_active']) {
                    $updates['consecutive_failures'] = 0;
                }
            }

            if (empty($updates)) {
                return 'No fields to update.';
            }

            $automation->update($updates);

            if (isset($updates['cron_expression']) || isset($updates['timezone'])) {
                $automation->refreshNextRunAt();
            }

            return "Automation updated: {$automation->name} (ID: {$automation->id})";
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
            'name' => $schema
                ->string()
                ->description('New name.'),
            'description' => $schema
                ->string()
                ->description('New description.'),
            'executionType' => $schema
                ->string()
                ->description("Switch execution type: 'prompt' or 'script'."),
            'prompt' => $schema
                ->string()
                ->description('New prompt (for prompt-type automations).'),
            'script' => $schema
                ->string()
                ->description('New synchronous JavaScript function body for a script automation. It is compiled before saving.'),
            'cronExpression' => $schema
                ->string()
                ->description('New cron expression.'),
            'timezone' => $schema
                ->string()
                ->description('New timezone.'),
            'agentId' => $schema
                ->string()
                ->description('New agent UUID.'),
            'channelId' => $schema
                ->string()
                ->description('New channel UUID.'),
            'isActive' => $schema
                ->boolean()
                ->description('Enable (true) or disable (false) the automation. Enabling also resets the failure counter.'),
        ];
    }
}
