<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListAgents implements Tool
{
    public function description(): string
    {
        return 'List all agents in the current workspace with their type, brain, status, and behavior mode.';
    }

    public function handle(Request $request): string
    {
        try {
            $agents = User::where('type', 'agent')
                ->where('workspace_id', workspace()->id)
                ->orderBy('name')
                ->get();

            if ($agents->isEmpty()) {
                return json_encode([]);
            }

            return json_encode($agents->map(fn ($agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'agentType' => $agent->agent_type,
                'brain' => $agent->brain,
                'status' => $agent->status ?? 'unknown',
                'behaviorMode' => $agent->behavior_mode ?? 'autonomous',
            ])->values()->toArray(), JSON_PRETTY_PRINT);
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
