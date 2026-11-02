<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Agent tool `list_routines`: lists the current workspace's routines with
 * kind, status, run count, next run and a last-result preview.
 */
class ListRoutines implements Tool
{
    public function description(): string
    {
        return 'List all routines in the workspace with their kind (agent/script/builtin), schedule, status (active/disabled), run count, next run time, and last result preview.';
    }

    public function handle(Request $request): string
    {
        try {
            $routines = Routine::forWorkspace()
                ->with('agent')
                ->orderBy('name')
                ->get();

            if ($routines->isEmpty()) {
                return 'No routines found.';
            }

            return $routines->map(function (Routine $a) {
                $status = $a->is_active ? 'active' : 'disabled';
                $kind = $a->kind;
                /** @var \Carbon\Carbon|null $nextRunAt */
                $nextRunAt = $a->next_run_at;
                $nextRun = $nextRunAt?->format('Y-m-d H:i T') ?? 'N/A';
                /** @var User|null $agent */
                $agent = $a->agent;
                $agentName = $agent->name ?? 'Unknown';

                $line = "- {$a->name} (ID: {$a->id}, kind: {$kind}, v{$a->version}, cron: {$a->cron_expression}, agent: {$agentName}, {$status}, runs: {$a->run_count}, next: {$nextRun})";

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
