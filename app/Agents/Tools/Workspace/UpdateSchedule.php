<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\User;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateSchedule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Update an existing scheduled automation (name, prompt, cron, timezone, agent, channel).';
    }

    public function handle(Request $request): string
    {
        try {
            $scheduleId = $request['scheduleId'] ?? null;
            if (!$scheduleId) {
                return 'scheduleId is required.';
            }

            $schedule = Automation::forWorkspace()->find($scheduleId);
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
                $targetAgent = \App\Models\User::where('type', 'agent')
                    ->where('workspace_id', $this->agent->workspace_id)
                    ->find($request['agentId']);
                if (!$targetAgent) {
                    return "Error: Agent not found in this workspace.";
                }
                $updates['agent_id'] = $request['agentId'];
            }
            if (isset($request['channelId'])) {
                $channel = \App\Models\Channel::forWorkspace()->find($request['channelId']);
                if (!$channel) {
                    return "Error: Channel not found in this workspace.";
                }
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
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scheduleId' => $schema
                ->string()
                ->description('Schedule UUID.')
                ->required(),
            'name' => $schema
                ->string()
                ->description('New schedule name.'),
            'prompt' => $schema
                ->string()
                ->description('The prompt to send to the agent.'),
            'cronExpression' => $schema
                ->string()
                ->description('Standard 5-field cron expression.'),
            'timezone' => $schema
                ->string()
                ->description("Timezone, e.g. 'America/New_York'."),
            'agentId' => $schema
                ->string()
                ->description('Agent UUID to assign the schedule to.'),
            'channelId' => $schema
                ->string()
                ->description('Channel UUID where the agent posts its response.'),
        ];
    }
}
