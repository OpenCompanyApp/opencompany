<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Agent tool `get_routine`: renders one routine of the current workspace
 * (configuration, content, status, recent runs) as markdown.
 */
class GetRoutine implements Tool
{
    public function description(): string
    {
        return 'Get full routine details including config, instructions/script content, execution history, and status.';
    }

    public function handle(Request $request): string
    {
        try {
            $routineId = $request['routineId'] ?? null;
            if (! $routineId) {
                return 'routineId is required.';
            }

            $routine = Routine::forWorkspace()->with('agent', 'channel')->find($routineId);
            if (! $routine) {
                return "Routine not found: {$routineId}";
            }

            /** @var User|null $agent */
            $agent = $routine->agent;
            /** @var Carbon|null $nextRunAt */
            $nextRunAt = $routine->next_run_at;
            /** @var Carbon|null $lastRunAt */
            $lastRunAt = $routine->last_run_at;

            $lines = [];
            $lines[] = "# {$routine->name}";
            $lines[] = '';

            // Config
            $lines[] = '## Configuration';
            $lines[] = "- **ID:** {$routine->id}";
            $lines[] = "- **Kind:** {$routine->kind}".($routine->built_in_key ? " ({$routine->built_in_key})" : '');
            $lines[] = "- **Version:** {$routine->version}";
            $lines[] = "- **Mode:** {$routine->mode}";
            $lines[] = "- **Notify:** {$routine->notify}";
            $lines[] = "- **Schedule (cron):** {$routine->cron_expression}";
            $lines[] = "- **Timezone:** {$routine->timezone}";
            $lines[] = '- **Agent:** '.($agent->name ?? ($routine->isBuiltin() ? 'None (built-in)' : 'Unknown'));
            $lines[] = '- **Channel:** '.($routine->channel->name ?? 'Auto-created');
            if ($routine->description) {
                $lines[] = "- **Description:** {$routine->description}";
            }
            $lines[] = '';

            // Status
            $lines[] = '## Status';
            $lines[] = '- **Active:** '.($routine->is_active ? 'Yes' : 'No');
            $lines[] = "- **Run count:** {$routine->run_count}";
            $lines[] = "- **Consecutive failures:** {$routine->consecutive_failures}";
            $lines[] = '- **Last run:** '.($lastRunAt?->format('Y-m-d H:i T') ?? 'Never');
            $lines[] = '- **Next run:** '.($nextRunAt?->format('Y-m-d H:i T') ?? 'N/A');

            // Next scheduled runs
            if ($routine->is_active && $routine->cron_expression) {
                $nextRuns = $routine->getNextRuns(3);
                if (! empty($nextRuns)) {
                    $lines[] = '- **Upcoming:** '.implode(', ', array_map(
                        fn ($run) => $run->format('Y-m-d H:i'),
                        $nextRuns
                    ));
                }
            }
            $lines[] = '';

            // Content
            $lines[] = '## Content';
            if ($routine->isScript()) {
                $lines[] = '```ruby';
                $lines[] = $routine->script;
                $lines[] = '```';
            } elseif ($routine->isBuiltin()) {
                $lines[] = (string) $routine->description;
            } else {
                $lines[] = (string) $routine->instructions;
            }
            $lines[] = '';

            // Execution history
            $limit = (int) ($request['historyLimit'] ?? 5);
            $history = Task::forWorkspace()->where('source', Task::SOURCE_ROUTINE)
                ->whereJsonContains('context->routine_id', $routine->id)
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
                            $line .= ' — Error: '.Str::limit($result['error'], 100);
                        } elseif (isset($result['output'])) {
                            $line .= ' — '.Str::limit($result['output'], 100);
                        } elseif (isset($result['response'])) {
                            $line .= ' — '.Str::limit($result['response'], 100);
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
            'routineId' => $schema
                ->string()
                ->description('Routine UUID.')
                ->required(),
            'historyLimit' => $schema
                ->integer()
                ->description('Number of recent runs to include. Default: 5.'),
        ];
    }
}
