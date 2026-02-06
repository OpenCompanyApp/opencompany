<?php

namespace App\Services;

use App\Models\AgentPermission;
use App\Models\User;

class AgentPermissionService
{
    /**
     * Resolve the final permission for a tool, combining DB permissions with behavior mode.
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

        // If allow record exists, use its requires_approval flag
        // If no record exists, tool is allowed by default (backwards-compatible)
        $dbRequiresApproval = $permission ? $permission->requires_approval : false;

        // Layer behavior mode on top
        $behaviorRequiresApproval = $this->behaviorModeRequiresApproval(
            $agent, $toolType
        );

        return [
            'allowed' => true,
            'requires_approval' => $dbRequiresApproval || $behaviorRequiresApproval,
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

        // No records = all integrations enabled (backward-compatible)
        if ($integrationPerms->isEmpty()) {
            return \App\Agents\Tools\ToolRegistry::INTEGRATION_APPS;
        }

        return $integrationPerms
            ->where('permission', 'allow')
            ->pluck('scope_key')
            ->toArray();
    }

    /**
     * Check if a specific integration is enabled for an agent.
     */
    public function isIntegrationEnabled(User $agent, string $appName): bool
    {
        return in_array($appName, $this->getEnabledIntegrations($agent));
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
