<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Channel;
use App\Models\Routine;
use App\Models\User;
use App\Services\ScriptAdmission;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Agent tool `create_routine`: creates an agent or script routine in the
 * acting agent's workspace.
 *
 * Validates the target agent and output channel against the workspace and
 * admits Ruby source with the pinned engine before persisting. A routine
 * grants nothing: its runs use the executing agent's ordinary permissions.
 * Built-in routines are code-defined and cannot be created here.
 */
class CreateRoutine implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a routine. "prompt" sends work to an agent; "script" runs validated synchronous Ruby on opencompany-code-v1 without model tokens. Scripts receive a read-only-style ctx data object. Discover APIs with code_read_doc and validate with code_exec before saving.';
    }

    public function handle(Request $request): string
    {
        try {
            $name = $request['name'] ?? null;
            $kind = $request['kind'] ?? Routine::KIND_AGENT;
            $mode = $request['mode'] ?? Routine::MODE_ACT;
            $notify = $request['notify'] ?? Routine::NOTIFY_ALWAYS;

            if (! $name) {
                return 'name is required.';
            }

            if (! in_array($kind, [Routine::KIND_AGENT, Routine::KIND_SCRIPT], true)) {
                return "kind must be 'agent' or 'script'.";
            }
            if (! in_array($mode, Routine::MODES, true)) {
                return "mode must be 'read_only' or 'act'.";
            }
            if (! in_array($notify, Routine::NOTIFIES, true)) {
                return "notify must be 'always', 'if_new' or 'on_failure'.";
            }

            // Validate kind-specific fields
            if ($kind === Routine::KIND_AGENT && empty($request['instructions'])) {
                return "instructions are required when kind is 'agent'.";
            }

            if ($kind === Routine::KIND_SCRIPT && empty($request['script'])) {
                return "script is required when kind is 'script'. Use code_read_doc to explore app.* and code_exec in validate mode before saving.";
            }

            $admission = $kind === Routine::KIND_SCRIPT
                ? app(ScriptAdmission::class)->admit((string) $request['script']) : [];

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

            $routine = Routine::create([
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'kind' => $kind,
                'description' => $request['description'] ?? null,
                'agent_id' => $agentId,
                'instructions' => $kind === Routine::KIND_AGENT ? $request['instructions'] : null,
                'script' => $kind === Routine::KIND_SCRIPT ? $request['script'] : null,
                ...$admission,
                'mode' => $mode,
                'notify' => $notify,
                'cron_expression' => $cronExpression,
                'timezone' => $request['timezone'] ?? 'UTC',
                'channel_id' => $request['channelId'] ?? null,
                'created_by_id' => $this->agent->id,
                'is_active' => true,
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
            ]);

            /** @var Carbon|null $nextRunAt */
            $nextRunAt = $routine->next_run_at;
            $nextRun = $nextRunAt?->format('Y-m-d H:i T');

            return "Routine created: {$routine->name} (ID: {$routine->id}, kind: {$kind}, cron: {$cronExpression}, next run: {$nextRun})";
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
                ->description('Routine name.')
                ->required(),
            'kind' => $schema
                ->string()
                ->description("'agent' (default) or 'script'."),
            'instructions' => $schema
                ->string()
                ->description("What the agent should do on each run. Required for 'agent' kind."),
            'script' => $schema
                ->string()
                ->description("Synchronous Ruby script. Required for 'script' kind; dynamic loading and unmanaged concurrency are unavailable."),
            'mode' => $schema
                ->string()
                ->description("'act' (default) or 'read_only'. Recorded now; read-only enforcement arrives later, so do not rely on it."),
            'notify' => $schema
                ->string()
                ->description("'always' (default), 'if_new' (skip output identical to the last posted one) or 'on_failure'."),
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
                ->description('Routine description.'),
        ];
    }
}
