<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetAutomation implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Get full automation details including config, prompt/script content, execution history, and status.';
    }

    public function handle(Request $request): string
    {
        try {
            $automationId = $request['automationId'] ?? null;
            if (! $automationId) {
                return 'automationId is required.';
            }

            $automation = Automation::forWorkspace()->with('agent', 'channel')->find($automationId);
            if (! $automation) {
                return "Automation not found: {$automationId}";
            }

            /** @var User|null $agent */
            $agent = $automation->agent;
            /** @var \Carbon\Carbon|null $nextRunAt */
            $nextRunAt = $automation->next_run_at;
            /** @var \Carbon\Carbon|null $lastRunAt */
            $lastRunAt = $automation->last_run_at;

            $lines = [];
            $lines[] = "# {$automation->name}";
            $lines[] = '';

            // Config
            $lines[] = '## Configuration';
            $lines[] = "- **ID:** {$automation->id}";
            $lines[] = "- **Trigger:** {$automation->trigger_type}";
            $lines[] = "- **Execution type:** " . ($automation->execution_type ?? 'prompt');
            $lines[] = "- **Cron:** {$automation->cron_expression}";
            $lines[] = "- **Timezone:** {$automation->timezone}";
            $lines[] = '- **Agent:** ' . ($agent->name ?? 'Unknown');
            $lines[] = '- **Channel:** ' . ($automation->channel?->name ?? 'Auto-created');
            if ($automation->description) {
                $lines[] = "- **Description:** {$automation->description}";
            }
            $lines[] = '';

            // Status
            $lines[] = '## Status';
            $lines[] = '- **Active:** ' . ($automation->is_active ? 'Yes' : 'No');
            $lines[] = "- **Run count:** {$automation->run_count}";
            $lines[] = "- **Consecutive failures:** {$automation->consecutive_failures}";
            $lines[] = '- **Last run:** ' . ($lastRunAt?->format('Y-m-d H:i T') ?? 'Never');
            $lines[] = '- **Next run:** ' . ($nextRunAt?->format('Y-m-d H:i T') ?? 'N/A');

            // Next scheduled runs
            if ($automation->is_active && $automation->cron_expression) {
                $nextRuns = $automation->getNextRuns(3);
                if (! empty($nextRuns)) {
                    $lines[] = '- **Upcoming:** ' . implode(', ', array_map(
                        fn ($run) => $run->format('Y-m-d H:i'),
                        $nextRuns
                    ));
                }
            }
            $lines[] = '';

            // Content
            $lines[] = '## Content';
            if ($automation->isScript()) {
                $lines[] = '```lua';
                $lines[] = $automation->script;
                $lines[] = '```';
            } else {
                $lines[] = $automation->prompt;
            }
            $lines[] = '';

            // Execution history
            $limit = (int) ($request['historyLimit'] ?? 5);
            $history = Task::where('source', Task::SOURCE_AUTOMATION)
                ->whereJsonContains('context->automation_id', $automation->id)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            if ($history->isNotEmpty()) {
                $lines[] = "## Recent Runs ({$history->count()})";
                foreach ($history as $task) {
                    $status = $task->status;
                    $time = $task->created_at->format('Y-m-d H:i');
                    $result = $task->result;

                    $line = "- [{$time}] **{$status}**";

                    if ($result) {
                        if (isset($result['error'])) {
                            $line .= ' — Error: ' . Str::limit($result['error'], 100);
                        } elseif (isset($result['output'])) {
                            $line .= ' — ' . Str::limit($result['output'], 100);
                        } elseif (isset($result['response'])) {
                            $line .= ' — ' . Str::limit($result['response'], 100);
                        }

                        if (isset($result['execution_time_ms'])) {
                            $line .= " ({$result['execution_time_ms']}ms)";
                        } elseif (isset($result['generation_time_ms'])) {
                            $line .= " ({$result['generation_time_ms']}ms)";
                        }
                    }

                    $lines[] = $line;
                }
            } else {
                $lines[] = '## Recent Runs';
                $lines[] = 'No execution history yet.';
            }

            return implode("\n", $lines);
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
            'historyLimit' => $schema
                ->integer()
                ->description('Number of recent runs to include. Default: 5.'),
        ];
    }
}
