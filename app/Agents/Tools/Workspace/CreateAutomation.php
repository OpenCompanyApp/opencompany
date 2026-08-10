<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\User;
use App\Services\QuickJsSandboxService;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateAutomation implements Tool
{
    public function __construct(
        private User $agent,
        private QuickJsSandboxService $sandbox,
    ) {}

    public function description(): string
    {
        return 'Create an automation. "prompt" sends work to an agent; "script" runs validated synchronous JavaScript on quickjs-v1 without model tokens. Scripts receive a read-only-style ctx data object. Discover APIs with code_read_doc and validate with code_exec before saving.';
    }

    public function handle(Request $request): string
    {
        try {
            $name = $request['name'] ?? null;
            $executionType = $request['executionType'] ?? 'prompt';

            if (! $name) {
                return 'name is required.';
            }

            if (! in_array($executionType, ['prompt', 'script'])) {
                return "executionType must be 'prompt' or 'script'.";
            }

            // Validate execution-specific fields
            if ($executionType === 'prompt' && empty($request['prompt'])) {
                return "prompt is required when executionType is 'prompt'.";
            }

            if ($executionType === 'script' && empty($request['script'])) {
                return "script is required when executionType is 'script'. Use code_read_doc to explore app.* and code_exec in validate mode before saving.";
            }

            if ($executionType === 'script') {
                $validationError = $this->validateScript((string) $request['script']);
                if ($validationError !== null) {
                    return $validationError;
                }
            }

            // Validate cron expression
            $cronExpression = $request['cronExpression'] ?? null;
            if (! $cronExpression) {
                return "cronExpression is required. Use standard 5-field format, e.g. '0 9 * * 1-5' for weekdays at 9am.";
            }

            if (! CronExpression::isValidExpression($cronExpression)) {
                return "Invalid cron expression: {$cronExpression}. Use standard 5-field format, e.g. '0 9 * * 1-5' for weekdays at 9am.";
            }

            $agentId = $request['agentId'] ?? $this->agent->id;

            // Validate agentId belongs to the same workspace
            if ($agentId !== $this->agent->id) {
                $targetAgent = User::where('type', 'agent')
                    ->where('workspace_id', $this->agent->workspace_id)
                    ->find($agentId);
                if (! $targetAgent) {
                    return 'Error: Agent not found in this workspace.';
                }
            }

            // Validate channelId belongs to workspace
            if (isset($request['channelId'])) {
                $channel = Channel::forWorkspace()->find($request['channelId']);
                if (! $channel) {
                    return 'Error: Channel not found in this workspace.';
                }
            }

            $automation = Automation::create([
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'trigger_type' => $request['triggerType'] ?? 'schedule',
                'execution_type' => $executionType,
                'description' => $request['description'] ?? null,
                'agent_id' => $agentId,
                'prompt' => $executionType === 'prompt' ? $request['prompt'] : null,
                'script' => $executionType === 'script' ? $request['script'] : null,
                'script_runtime' => $executionType === 'script' ? config('code.runtime') : null,
                'cron_expression' => $cronExpression,
                'timezone' => $request['timezone'] ?? 'UTC',
                'channel_id' => $request['channelId'] ?? null,
                'created_by_id' => $this->agent->id,
                'is_active' => true,
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
            ]);

            /** @var Carbon|null $nextRunAt */
            $nextRunAt = $automation->next_run_at;
            $nextRun = $nextRunAt?->format('Y-m-d H:i T');

            return "Automation created: {$automation->name} (ID: {$automation->id}, type: {$executionType}, cron: {$cronExpression}, next run: {$nextRun})";
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
                ->description('Automation name.')
                ->required(),
            'executionType' => $schema
                ->string()
                ->description("'prompt' (default) or 'script'."),
            'prompt' => $schema
                ->string()
                ->description("Agent prompt. Required for 'prompt' type."),
            'script' => $schema
                ->string()
                ->description("Synchronous JavaScript function body. Required for 'script' type; modules and Promise jobs are unavailable."),
            'cronExpression' => $schema
                ->string()
                ->description("5-field cron, e.g. '0 9 * * 1-5'.")
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('Agent UUID. Defaults to self.'),
            'channelId' => $schema
                ->string()
                ->description('Channel for output.'),
            'timezone' => $schema
                ->string()
                ->description("Defaults to 'UTC'."),
            'description' => $schema
                ->string()
                ->description('Automation description.'),
        ];
    }

    /**
     * Compile without exposing app.* so creation cannot schedule invalid code.
     */
    private function validateScript(string $script): ?string
    {
        $result = $this->sandbox->execute(
            code: $script,
            profile: 'automation',
            validateOnly: true,
            sourceName: 'automation-code.js',
        );

        if ($result->succeeded()) {
            return null;
        }

        $error = $result->error ?? [];

        return 'JavaScript validation failed ['.($error['type'] ?? 'syntax_error').']'
            .(isset($error['line']) ? ' at line '.$error['line'] : '')
            .': '.($error['message'] ?? 'Invalid source.');
    }
}
