<?php

namespace App\Agents\Tools\Workspace;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgent implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return "Update an existing agent\x27s name, brain, status, behavior mode, or approval requirement.";
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            if (!$agentId) {
                return 'agentId is required.';
            }

            $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
            if (!$target) {
                return "Agent not found: {$agentId}";
            }

            // Self-escalation guard
            if ($this->agent->id === $agentId) {
                if (isset($request['behaviorMode'])) {
                    $modes = ['strict' => 0, 'supervised' => 1, 'autonomous' => 2];
                    $currentLevel = $modes[$this->agent->behavior_mode ?? 'autonomous'] ?? 2;
                    $newLevel = $modes[$request['behaviorMode']] ?? 2;
                    if ($newLevel > $currentLevel) {
                        return 'Self-escalation denied: you cannot set your own behavior mode to a less restrictive level.';
                    }
                }
                if (isset($request['mustWaitForApproval']) && $request['mustWaitForApproval'] === 'false') {
                    if ($this->agent->must_wait_for_approval) {
                        return 'Self-escalation denied: you cannot disable your own approval requirement.';
                    }
                }
            }

            $updates = [];

            if (isset($request['name'])) {
                $updates['name'] = $request['name'];
            }
            if (isset($request['brain'])) {
                if (!str_contains($request['brain'], ':')) {
                    return 'Invalid brain format. Expected "provider:model".';
                }
                $updates['brain'] = $request['brain'];
            }
            if (isset($request['status'])) {
                $updates['status'] = $request['status'];
            }
            if (isset($request['behaviorMode'])) {
                $updates['behavior_mode'] = $request['behaviorMode'];
            }
            if (isset($request['mustWaitForApproval'])) {
                $updates['must_wait_for_approval'] = filter_var($request['mustWaitForApproval'], FILTER_VALIDATE_BOOLEAN);
            }

            if (empty($updates)) {
                return 'No fields to update. Provide at least one of: name, brain, status, behaviorMode, mustWaitForApproval.';
            }

            $target->update($updates);

            return "Agent updated: {$target->name} (ID: {$target->id})";
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
            'name' => $schema
                ->string()
                ->description('New agent name.'),
            'brain' => $schema
                ->string()
                ->description('AI model in "provider:model" format.'),
            'status' => $schema
                ->string()
                ->description('Agent status: "idle", "working", "offline", "awaiting_approval".'),
            'behaviorMode' => $schema
                ->string()
                ->description('Behavior mode: "autonomous", "supervised", or "strict".'),
            'mustWaitForApproval' => $schema
                ->string()
                ->description('Whether agent must wait for approval: "true" or "false".'),
        ];
    }
}