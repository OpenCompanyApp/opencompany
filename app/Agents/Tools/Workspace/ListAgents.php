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
                return 'No agents found in workspace.';
            }

            $lines = ["Agents ({$agents->count()}):"];
            foreach ($agents as $agent) {
                $status = $agent->status ?? 'unknown';
                $behavior = $agent->behavior_mode ?? 'autonomous';
                $lines[] = "- {$agent->name} (ID: {$agent->id}) \xe2\x80\x94 type: {$agent->agent_type}, brain: {$agent->brain}, status: {$status}, behavior: {$behavior}";
            }

            return implode("\n", $lines);
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
