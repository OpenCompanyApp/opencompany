<?php

namespace App\Agents\Tools\Workspace;

use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgentIntegrationAccess implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Update integration access permissions for an agent. You can only enable integrations you have access to.';
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

            $raw = $request['integrations'] ?? [];
            $integrations = is_array($raw) ? $raw : json_decode($raw, true);

            if (!is_array($integrations)) {
                return 'Invalid integrations parameter. Expected array of app names (e.g., {"telegram","plausible"}).';
            }

            // Privilege escalation check: can only enable integrations the caller has
            $callerIntegrations = $this->permissionService->getEnabledIntegrations($this->agent);
            $unauthorized = array_diff($integrations, $callerIntegrations);

            if ($unauthorized) {
                return "Privilege escalation denied: you don't have these integrations enabled: " . implode(', ', $unauthorized);
            }

            AgentPermission::where('agent_id', $target->id)->where('scope_type', 'integration')->delete();

            foreach (ToolRegistry::INTEGRATION_APPS as $app) {
                AgentPermission::create([
                    'id' => Str::uuid()->toString(),
                    'agent_id' => $target->id,
                    'scope_type' => 'integration',
                    'scope_key' => $app,
                    'permission' => in_array($app, $integrations) ? 'allow' : 'deny',
                    'requires_approval' => false,
                ]);
            }

            return "Integration permissions updated for {$target->name}. Enabled: " . ($integrations ? implode(', ', $integrations) : 'none') . '.';
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
            'integrations' => $schema
                ->string()
                ->description('JSON array of integration app names, e.g. ["telegram","plausible"].')
                ->required(),
        ];
    }
}