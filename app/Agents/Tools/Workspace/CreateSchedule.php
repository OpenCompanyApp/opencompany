<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\User;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateSchedule implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Create a new scheduled automation with a cron expression and prompt for an agent.';
    }

    public function handle(Request $request): string
    {
        try {
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

            $schedule = Automation::create([
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
                'workspace_id' => $this->agent->workspace_id ?? workspace()->id,
            ]);

            /** @var \Carbon\Carbon|null $nextRunAt */
            $nextRunAt = $schedule->next_run_at;
            $nextRun = $nextRunAt?->format('Y-m-d H:i T');

            return "Schedule created: {$schedule->name} (ID: {$schedule->id}, cron: {$cronExpression}, next run: {$nextRun})";
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
                ->description('Schedule name.')
                ->required(),
            'prompt' => $schema
                ->string()
                ->description('The prompt to send to the agent on each scheduled run.')
                ->required(),
            'cronExpression' => $schema
                ->string()
                ->description("Standard 5-field cron expression. E.g. '0 9 * * 1-5' for weekdays at 9am.")
                ->required(),
            'agentId' => $schema
                ->string()
                ->description('Agent UUID to assign the schedule to. Defaults to self.'),
            'channelId' => $schema
                ->string()
                ->description('Channel UUID where the agent posts its response.'),
            'timezone' => $schema
                ->string()
                ->description("Timezone, e.g. 'America/New_York'. Defaults to 'UTC'."),
            'description' => $schema
                ->string()
                ->description('Description of the schedule.'),
        ];
    }
}
