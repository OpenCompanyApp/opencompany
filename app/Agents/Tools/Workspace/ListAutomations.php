<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListAutomations implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all automations in the workspace with their trigger type, execution type (prompt/script), status (active/disabled), run count, next run time, and last result preview.';
    }

    public function handle(Request $request): string
    {
        try {
            $automations = Automation::forWorkspace()
                ->with('agent')
                ->orderBy('name')
                ->get();

            if ($automations->isEmpty()) {
                return 'No automations found.';
            }

            return $automations->map(function (Automation $a) {
                $status = $a->is_active ? 'active' : 'disabled';
                $type = $a->execution_type ?? 'prompt';
                /** @var \Carbon\Carbon|null $nextRunAt */
                $nextRunAt = $a->next_run_at;
                $nextRun = $nextRunAt?->format('Y-m-d H:i T') ?? 'N/A';
                /** @var User|null $agent */
                $agent = $a->agent;
                $agentName = $agent->name ?? 'Unknown';

                $line = "- {$a->name} (ID: {$a->id}, type: {$type}, trigger: {$a->trigger_type}, agent: {$agentName}, {$status}, runs: {$a->run_count}, next: {$nextRun})";

                // Add last result preview
                if ($a->last_result) {
                    if (isset($a->last_result['error'])) {
                        $line .= "\n  Last run: FAILED — " . Str::limit($a->last_result['error'], 100);
                    } elseif (isset($a->last_result['output'])) {
                        $line .= "\n  Last run: OK — " . Str::limit($a->last_result['output'], 100);
                    } elseif (isset($a->last_result['response_preview'])) {
                        $line .= "\n  Last run: OK — " . Str::limit($a->last_result['response_preview'], 100);
                    }
                }

                return $line;
            })->join("\n");
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
