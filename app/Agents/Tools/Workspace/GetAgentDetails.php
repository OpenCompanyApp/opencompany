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

            return json_encode([
                'id' => $agent->id,
                'name' => $agent->name,
                'agentType' => $agent->agent_type,
                'brain' => $agent->brain,
                'status' => $agent->status,
                'behaviorMode' => $agent->behavior_mode ?? 'autonomous',
                'mustWaitForApproval' => $agent->must_wait_for_approval,
                'currentTask' => $agent->current_task,
                'tasks' => [
                    'completed' => (int) ($taskStats->completed ?? 0),
                    'total' => (int) ($taskStats->total ?? 0),
                ],
            ], JSON_PRETTY_PRINT);
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