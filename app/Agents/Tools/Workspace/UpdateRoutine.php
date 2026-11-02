<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Channel;
use App\Models\Routine;
use App\Models\User;
use App\Services\ScriptAdmission;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Agent tool `update_routine`: edits an agent or script routine in the
 * acting agent's workspace.
 *
 * Changing instructions or script bumps the routine version (model hook).
 * New Ruby is admitted before saving, and a stale script cannot be
 * re-enabled. Built-in routines are system-managed and refused.
 */
class UpdateRoutine implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Update a routine's configuration. Setting isActive to true resets the failure counter.";
    }

    public function handle(Request $request): string
    {
        try {
            $routineId = $request['routineId'] ?? null;
            if (! $routineId) {
                return 'routineId is required.';
            }

            $routine = Routine::forWorkspace()->find($routineId);
            if (! $routine) {
                return "Routine not found: {$routineId}";
            }
            if ($routine->isBuiltin()) {
                return 'Built-in routines are managed by OpenCompany and cannot be edited.';
            }

            $updates = [];

            if (isset($request['name'])) {
                $updates['name'] = $request['name'];
            }
            if (isset($request['description'])) {
                $updates['description'] = $request['description'];
            }
            if (isset($request['kind'])) {
                if (! in_array($request['kind'], [Routine::KIND_AGENT, Routine::KIND_SCRIPT], true)) {
                    return "kind must be 'agent' or 'script'.";
                }
                $updates['kind'] = $request['kind'];
            }
            if (isset($request['instructions'])) {
                $updates['instructions'] = $request['instructions'];
            }
            if (isset($request['mode'])) {
                if (! in_array($request['mode'], Routine::MODES, true)) {
                    return "mode must be 'read_only' or 'act'.";
                }
                $updates['mode'] = $request['mode'];
            }
            if (isset($request['notify'])) {
                if (! in_array($request['notify'], Routine::NOTIFIES, true)) {
                    return "notify must be 'always', 'if_new' or 'on_failure'.";
                }
                $updates['notify'] = $request['notify'];
            }
            if (isset($request['script'])) {
                $updates['script'] = $request['script'];
            }

            $targetKind = $updates['kind'] ?? $routine->kind;
            if ($targetKind === Routine::KIND_SCRIPT && isset($request['script'])) {
                $updates = [...$updates, ...app(ScriptAdmission::class)->admit((string) $request['script'])];
            } elseif ($targetKind === Routine::KIND_SCRIPT && (bool) ($request['isActive'] ?? $routine->is_active)
                && ! app(ScriptAdmission::class)->matches($routine)) {
                return 'Submit and validate a Ruby body before enabling this stale routine.';
            } elseif ($targetKind === Routine::KIND_AGENT) {
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

            $routine->update($updates);

            if (isset($updates['cron_expression']) || isset($updates['timezone'])) {
                $routine->refreshNextRunAt();
            }

            return "Routine updated: {$routine->name} (ID: {$routine->id})";
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'routineId' => $schema
                ->string()
                ->description('Routine UUID.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('New name.'),
            'description' => $schema
                ->string()
                ->description('New description.'),
            'kind' => $schema
                ->string()
                ->description("Switch kind: 'agent' or 'script'."),
            'instructions' => $schema
                ->string()
                ->description('New instructions (for agent routines). Bumps the routine version.'),
            'mode' => $schema
                ->string()
                ->description("'act' or 'read_only' (recorded; enforcement arrives later)."),
            'notify' => $schema
                ->string()
                ->description("'always', 'if_new' or 'on_failure'."),
            'script' => $schema
                ->string()
                ->description('New synchronous Ruby script for a script routine. It is compiled before saving.'),
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
                ->description('Enable (true) or disable (false) the routine. Enabling also resets the failure counter.'),
        ];
    }
}
