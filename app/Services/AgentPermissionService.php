<?php

namespace App\Services;

use App\Models\AgentPermission;
use App\Models\User;
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
     */
    public function canAccessChannel(User $agent, string $channelId): bool
    {
        $channelPerms = AgentPermission::forAgent($agent->id)
            ->channels()
            ->allowed()
            ->pluck('scope_key');

        // No channel restrictions set — allow all member channels
        if ($channelPerms->isEmpty()) {
            return true;
        }

        return $channelPerms->contains($channelId);
    }

    /**
     * Get the list of channel IDs an agent can access.
     *
     * Returns null if unrestricted (no channel permissions set).
     * Returns array of channel UUIDs if restricted.
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

        // No records = all integrations enabled (backward-compatible)
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
     * @return array{allowed: bool, requires_approval: bool}
     */
    public function canContactAgent(User $caller, User $target): array
    {
        // Manager hierarchy bypass — always allow without approval
        if ($target->id === $caller->manager_id || $caller->id === $target->manager_id) {
            return ['allowed' => true, 'requires_approval' => false];
        }

        // Check explicit agent-scoped permission
        $explicit = AgentPermission::forAgent($caller->id)
            ->where('scope_type', 'agent')
            ->where('scope_key', $target->id)
            ->first();

        if ($explicit) {
            if ($explicit->permission === 'deny') {
                return ['allowed' => false, 'requires_approval' => false];
            }

            return ['allowed' => true, 'requires_approval' => $explicit->requires_approval];
        }

        // Check wildcard agent permission
        $wildcard = AgentPermission::forAgent($caller->id)
            ->where('scope_type', 'agent')
            ->where('scope_key', '*')
            ->first();

        if ($wildcard) {
            if ($wildcard->permission === 'deny') {
                return ['allowed' => false, 'requires_approval' => false];
            }

            return ['allowed' => true, 'requires_approval' => $wildcard->requires_approval];
        }

        // Default: allow, layer behavior mode
        $requiresApproval = $this->behaviorModeRequiresApproval($caller, 'write');

        return ['allowed' => true, 'requires_approval' => $requiresApproval];
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
