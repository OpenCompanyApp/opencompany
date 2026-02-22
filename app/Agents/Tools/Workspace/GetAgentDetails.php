<?php

namespace App\Agents\Tools\Workspace;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetAgentDetails implements Tool
{
    public function description(): string
    {
        return 'Get detailed information about a specific agent including task statistics.';
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            if (!$agentId) {
                return 'agentId is required.';
            }

            $agent = User::where('type', 'agent')->where('workspace_id', workspace()->id)->find($agentId);
            if (!$agent) {
                return "Agent not found: {$agentId}";
            }

            $taskStats = Task::forWorkspace()->where('agent_id', $agent->id)
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
                ->first();

            $lines = [
                "Agent: {$agent->name}",
                "ID: {$agent->id}",
                "Type: {$agent->agent_type}",
                "Brain: {$agent->brain}",
                "Status: {$agent->status}",
                "Behavior Mode: " . ($agent->behavior_mode ?? 'autonomous'),
                "Must Wait For Approval: " . ($agent->must_wait_for_approval ? 'yes' : 'no'),
                "Current Task: " . ($agent->current_task ?? 'none'),
                "Tasks: " . (int) ($taskStats->completed ?? 0) . " completed / " . (int) ($taskStats->total ?? 0) . " total",
            ];

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agentId' => $schema
                ->string()
                ->description('Agent UUID.')
                ->required(),
        ];
    }
}