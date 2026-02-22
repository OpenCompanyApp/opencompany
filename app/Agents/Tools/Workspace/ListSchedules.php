<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Automation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListSchedules implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'List all scheduled automations in the workspace.';
    }

    public function handle(Request $request): string
    {
        try {
            $schedules = Automation::forWorkspace()
                ->with('agent')
                ->orderBy('name')
                ->get();

            if ($schedules->isEmpty()) {
                return 'No automations found.';
            }

            return $schedules->map(function (Automation $s) {
                $status = $s->is_active ? 'active' : 'disabled';
                /** @var \Carbon\Carbon|null $nextRunAt */
                $nextRunAt = $s->next_run_at;
                $nextRun = $nextRunAt?->format('Y-m-d H:i T') ?? 'N/A';
                /** @var User|null $agent */
                $agent = $s->agent;
                $agentName = $agent->name ?? 'Unknown';

                return "- {$s->name} (ID: {$s->id}, agent: {$agentName}, {$status}, runs: {$s->run_count}, next: {$nextRun})";
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
