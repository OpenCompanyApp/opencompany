<?php

namespace App\Services;

use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

class AgentPermissionService
{
    /**
     * Tools that should never require approval regardless of behavior_mode or DB settings.
     * These are system/control-flow tools where requiring approval would be nonsensical
     * (e.g. wait_for_approval requiring approval creates an infinite loop).
     */
    private const APPROVAL_EXEMPT_TOOLS = [
        'wait_for_approval',
        'wait',
        'update_current_task',
        'create_task_step',
        'get_tool_info',
        'contact_agent',
    ];

    public function __construct(
        private ToolProviderRegistry $providerRegistry,
    ) {}
    /**
     * Resolve the final permission for a tool, combining DB permissions with behavior mode.
     *
     * Priority: deny → exempt tools → explicit DB record → behavior_mode default.
     *
     * @return array{allowed: bool, requires_approval: bool}
     */
    public function resolveToolPermission(User $agent, string $toolSlug, string $toolType): array
    {
        $permission = AgentPermission::forAgent($agent->id)
            ->tools()
            ->where('scope_key', $toolSlug)
            ->first();

        // If explicit deny record exists, tool is not allowed
        if ($permission && $permission->permission === 'deny') {
            return ['allowed' => false, 'requires_approval' => false];
        }

        // System/control-flow tools never require approval
        if (in_array($toolSlug, self::APPROVAL_EXEMPT_TOOLS)) {
            return ['allowed' => true, 'requires_approval' => false];
        }

        // Explicit DB record overrides behavior mode
        if ($permission) {
            return [
                'allowed' => true,
                'requires_approval' => $permission->requires_approval,
            ];
        }

        // No explicit record — fall back to behavior mode defaults
        return [
            'allowed' => true,
            'requires_approval' => $this->behaviorModeRequiresApproval($agent, $toolType),
        ];
    }

    /**
     * Check if an agent can send messages to a specific channel.
     *
     * If the agent has ANY channel-scoped permissions, only those channels are allowed.
     * If the agent has zero channel-scoped permissions, all channels the agent is a member of are allowed.
     *
     * @return array{allowed: bool, can_request: bool}
     */
    public function canAccessChannel(User $agent, string $channelId): array
    {
        $channelPerms = AgentPermission::forAgent($agent->id)
            ->channels()
            ->allowed()
            ->pluck('scope_key');

        // No channel restrictions set — allow all member channels
        if ($channelPerms->isEmpty()) {
            return ['allowed' => true, 'can_request' => false];
        }

        if ($channelPerms->contains($channelId)) {
            return ['allowed' => true, 'can_request' => false];
        }

        // Channel not in whitelist — agent can request access
        return ['allowed' => false, 'can_request' => true];
    }

    /**
     * Get the list of channel IDs an agent can access.
     *
     * Returns null if unrestricted (no channel permissions set).
     * Returns array of channel UUIDs if restricted.
     *
     * @return array<int, string>|null
     */
    public function getAllowedChannelIds(User $agent): ?array
    {
        $channelPerms = AgentPermission::forAgent($agent->id)
            ->channels()
            ->allowed()
            ->pluck('scope_key');

        if ($channelPerms->isEmpty()) {
            return null;
        }

        return $channelPerms->toArray();
    }

    /**
     * Get the list of document folder IDs an agent can search.
     *
     * Returns null if unrestricted (no folder permissions set).
     * Returns array of folder UUIDs if restricted.
     *
     * @return array<int, string>|null
     */
    public function getAllowedFolderIds(User $agent): ?array
    {
        $folderPerms = AgentPermission::forAgent($agent->id)
            ->folders()
            ->allowed()
            ->pluck('scope_key');

        if ($folderPerms->isEmpty()) {
            return null; // Unrestricted
        }

        return $folderPerms->toArray();
    }

    /**
     * Get the list of enabled integrations for an agent.
     * If no integration records exist, all integrations are enabled (backward-compatible).
     *
     * @return string[] List of enabled integration app names
     */
    public function getEnabledIntegrations(User $agent): array
    {
        $integrationPerms = AgentPermission::forAgent($agent->id)
            ->where('scope_type', 'integration')
            ->get();

        // Build full list of all integration app names
        $allApps = \App\Agents\Tools\ToolRegistry::INTEGRATION_APPS;
        foreach ($this->providerRegistry->all() as $provider) {
            if ($provider->isIntegration() && !in_array($provider->appName(), $allApps)) {
                $allApps[] = $provider->appName();
            }
        }

        // Filter by workspace-level enablement.
        // MCP servers (mcp_ prefix) are passthrough — they self-register only when enabled.
        if (app()->bound('currentWorkspace')) {
            $workspaceEnabledIds = IntegrationSetting::forWorkspace()
                ->where('enabled', true)
                ->pluck('integration_id')
                ->toArray();

            $allApps = array_values(array_filter($allApps, function (string $app) use ($workspaceEnabledIds) {
                return str_starts_with($app, 'mcp_') || in_array($app, $workspaceEnabledIds);
            }));
        }

        // No agent-level records = all workspace-enabled integrations allowed
        if ($integrationPerms->isEmpty()) {
            return $allApps;
        }

        // When records exist: explicitly denied integrations are blocked,
        // explicitly allowed and new (unrecorded) integrations are enabled.
        // This ensures newly installed packages work without manual permission updates.
        $denied = $integrationPerms
            ->where('permission', 'deny')
            ->pluck('scope_key')
            ->toArray();

        return array_values(array_filter($allApps, fn ($app) => !in_array($app, $denied)));
    }

    /**
     * Check if a specific integration is enabled for an agent.
     */
    public function isIntegrationEnabled(User $agent, string $appName): bool
    {
        return in_array($appName, $this->getEnabledIntegrations($agent));
    }

    /**
     * Check if an agent is allowed to contact another agent.
     *
     * @return array{allowed: bool, requires_approval: bool, can_request: bool}
     */
    public function canContactAgent(User $caller, User $target): array
    {
        // Manager hierarchy bypass — always allow without approval
        if ($target->id === $caller->manager_id || $caller->id === $target->manager_id) {
            return ['allowed' => true, 'requires_approval' => false, 'can_request' => false];
        }

        // Check explicit agent-scoped permission
        $explicit = AgentPermission::forAgent($caller->id)
            ->where('scope_type', 'agent')
            ->where('scope_key', $target->id)
            ->first();

        if ($explicit) {
            if ($explicit->permission === 'deny') {
                return ['allowed' => false, 'requires_approval' => false, 'can_request' => true];
            }

            return ['allowed' => true, 'requires_approval' => $explicit->requires_approval, 'can_request' => false];
        }

        // Check wildcard agent permission
        $wildcard = AgentPermission::forAgent($caller->id)
            ->where('scope_type', 'agent')
            ->where('scope_key', '*')
            ->first();

        if ($wildcard) {
            if ($wildcard->permission === 'deny') {
                return ['allowed' => false, 'requires_approval' => false, 'can_request' => true];
            }

            return ['allowed' => true, 'requires_approval' => $wildcard->requires_approval, 'can_request' => false];
        }

        // Default: allow, layer behavior mode
        $requiresApproval = $this->behaviorModeRequiresApproval($caller, 'write');

        return ['allowed' => true, 'requires_approval' => $requiresApproval, 'can_request' => false];
    }

    /**
     * Create an access-type approval request for a denied resource.
     */
    public function createAccessRequest(
        User $agent,
        string $scopeType,
        string $scopeKey,
        string $description,
    ): ApprovalRequest {
        return ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'type' => 'access',
            'title' => "Access request: {$scopeType} — {$scopeKey}",
            'description' => $description,
            'requester_id' => $agent->id,
            'status' => 'pending',
            'tool_execution_context' => [
                'scope_type' => $scopeType,
                'scope_key' => $scopeKey,
            ],
        ]);
    }

    /**
     * Determine if the behavior mode requires approval for a given tool type.
     *
     * - autonomous: no additional approval requirement
     * - supervised: write tools require approval
     * - strict: ALL tools require approval
     */
    private function behaviorModeRequiresApproval(User $agent, string $toolType): bool
    {
        $mode = $agent->behavior_mode ?? 'autonomous';

        return match ($mode) {
            'strict' => true,
            'supervised' => $toolType === 'write',
            default => false,
        };
    }
}
