<?php

namespace App\Agents\Tools\Workspace;

use App\Models\AgentPermission;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgentToolPermissions implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update tool permissions for an agent. You can only grant permissions you yourself have.';
    }

    public function handle(Request $request): string
    {
        try {
            $agentId = $request['agentId'] ?? null;
            if (!$agentId) {
                return 'agentId is required.';
            }

            // Self-modification guard
            if ($this->agent->id === $agentId) {
                return 'Self-modification denied: you cannot change your own permissions. Ask a human or another agent.';
            }

            $target = User::where('type', 'agent')->where('workspace_id', $this->agent->workspace_id)->find($agentId);
            if (!$target) {
                return "Agent not found: {$agentId}";
            }

            $toolsJson = $request['tools'] ?? null;
            if (!$toolsJson) {
                return 'tools is required. JSON array: [{"scopeKey":"slug","permission":"allow|deny","requiresApproval":true|false}]';
            }

            $tools = is_array($toolsJson) ? $toolsJson : json_decode($toolsJson, true);
            if (!is_array($tools)) {
                return 'Invalid tools parameter. Expected array.';
            }

            // Privilege escalation check: caller can only grant tools it has
            $denied = [];
            $escalated = [];
            foreach ($tools as $tool) {
                if (empty($tool['scopeKey']) || empty($tool['permission'])) {
                    continue;
                }

                if ($tool['permission'] === 'allow') {
                    $callerPerm = $this->permissionService->resolveToolPermission(
                        $this->agent, $tool['scopeKey'], 'write' // check as write (strictest)
                    );

                    if (!$callerPerm['allowed']) {
                        $denied[] = $tool['scopeKey'];
                        continue;
                    }

                    // Cannot remove approval requirement if caller has it
                    $wantsNoApproval = !($tool['requiresApproval'] ?? false);
                    if ($wantsNoApproval && $callerPerm['requires_approval']) {
                        $escalated[] = $tool['scopeKey'];
                        continue;
                    }
                }
            }

            if ($denied) {
                return "Privilege escalation denied: you don't have access to these tools and can't grant them: " . implode(', ', $denied);
            }
            if ($escalated) {
                return "Privilege escalation denied: you require approval for these tools and can't waive it for others: " . implode(', ', $escalated);
            }

            // All checks passed — apply
            AgentPermission::where('agent_id', $target->id)->where('scope_type', 'tool')->delete();

            foreach ($tools as $tool) {
                if (empty($tool['scopeKey']) || empty($tool['permission'])) {
                    continue;
                }

                AgentPermission::create([
                    'id' => Str::uuid()->toString(),
                    'agent_id' => $target->id,
                    'scope_type' => 'tool',
                    'scope_key' => $tool['scopeKey'],
                    'permission' => $tool['permission'],
                    'requires_approval' => $tool['requiresApproval'] ?? false,
                ]);
            }

            return "Tool permissions updated for {$target->name}. Set " . count($tools) . " tool permission(s).";
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
                ->description('Target agent UUID.')
                ->required(),
            'tools' => $schema
                ->string()
                ->description('JSON array: [{"scopeKey":"tool_slug","permission":"allow|deny","requiresApproval":true|false}]')
                ->required(),
        ];
    }
}