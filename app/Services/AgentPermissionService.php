<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Resolves agent permissions from stored scope records and behavior mode.
 *
 * This service is the compatibility layer between old "unrestricted by default"
 * agent behavior and newer explicit scopes for tools, channels, integrations,
 * agents, and file folders. Keep deny rules and workspace-enabled filtering
 * visible because runtime evaluators depend on this shape.
 */
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
        'update_task',
        'add_task_step',
        'update_task_step',
        'set_task_status',
        'create_task_step',
        'contact_agent',
    ];

    public function __construct() {}

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

        // Deny records always win. A later approval or behavior-mode default
        // must not accidentally re-enable a tool explicitly blocked by an admin.
        if ($permission && $permission->permission === 'deny') {
            return ['allowed' => false, 'requires_approval' => false];
        }

        // System/control-flow tools never require approval because some are the
        // mechanism used to wait for or record approval decisions.
        if (in_array($toolSlug, self::APPROVAL_EXEMPT_TOOLS)) {
            return ['allowed' => true, 'requires_approval' => false];
        }

        // Explicit allow records can still require approval. That lets admins
        // permit a tool but keep human review on specific high-risk actions.
        if ($permission) {
            return [
                'allowed' => true,
                'requires_approval' => $permission->requires_approval,
            ];
        }

        // No explicit record means the agent's behavior mode supplies the
        // default approval posture for read/write tools.
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

        // Start from static app integrations, then include package/MCP providers
        // discovered at runtime. This avoids manual permission updates whenever
        // a new package integration is installed.
        $allApps = ToolRegistry::INTEGRATION_APPS;
        foreach ($this->integrationProviders() as $provider) {
            if ($provider->isIntegration() && ! in_array($provider->appName(), $allApps)) {
                $allApps[] = $provider->appName();
            }
        }

        // Filter by workspace-level enablement before agent-level denies. MCP
        // servers are passthrough because only enabled servers self-register.
        if (app()->bound('currentWorkspace')) {
            $workspaceEnabledIds = IntegrationSetting::forWorkspace()
                ->where('enabled', true)
                ->pluck('integration_id')
                ->toArray();

            $allApps = array_values(array_filter($allApps, function (string $app) use ($workspaceEnabledIds) {
                return str_starts_with($app, 'mcp_') || in_array($app, $workspaceEnabledIds);
            }));
        }

        // Backward compatibility: agents with no integration records inherit all
        // workspace-enabled integrations.
        if ($integrationPerms->isEmpty()) {
            return $allApps;
        }

        // When records exist, denies block specific integrations while new
        // unrecorded integrations remain enabled. This matches package install
        // behavior and avoids a hidden "default deny forever" trap.
        $denied = $integrationPerms
            ->where('permission', 'deny')
            ->pluck('scope_key')
            ->toArray();

        return array_values(array_filter($allApps, fn ($app) => ! in_array($app, $denied)));
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
        // Manager hierarchy is a structural trust relationship. Keep it above
        // explicit agent permissions so manager/subordinate coordination cannot
        // be accidentally blocked by missing records.
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

        // Wildcard agent permission applies after explicit target records. That
        // lets admins deny one sensitive peer while allowing general contact.
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

        // Default contact is allowed, with behavior mode deciding whether a
        // write-like interagent request requires approval.
        $requiresApproval = $this->behaviorModeRequiresApproval($caller, 'write');

        return ['allowed' => true, 'requires_approval' => $requiresApproval, 'can_request' => false];
    }

    /**
     * Get the list of file folder IDs an agent has been explicitly granted access to.
     *
     * Returns null if wildcard (*) access is set (unrestricted).
     * Returns empty array if no explicit grants (home folder only).
     * Returns array of folder UUIDs if specific grants exist.
     *
     * @return array<int, string>|null
     */
    public function getAllowedFileFolderIds(User $agent): ?array
    {
        $perms = AgentPermission::forAgent($agent->id)
            ->where('scope_type', 'file_folder')
            ->allowed()
            ->pluck('scope_key');

        if ($perms->contains('*')) {
            return null; // Unrestricted
        }

        return $perms->toArray();
    }

    /**
     * Check if an agent can access a specific file or folder.
     *
     * Rules:
     * 1. Agent always has access to their own home folder (/agents/{slug}/) and descendants
     * 2. Explicit file_folder permissions grant access to a folder and its descendants
     * 3. Wildcard (*) grants unrestricted access
     * 4. Default (no permissions): home folder only
     */
    public function canAccessFilePath(User $agent, WorkspaceFile $file): bool
    {
        $allowedIds = $this->getAllowedFileFolderIds($agent);

        // Wildcard grants unrestricted file-folder access inside the workspace.
        if ($allowedIds === null) {
            return true;
        }

        // Agents always keep their own generated home folder even when no
        // broader file-folder permissions have been granted.
        if ($this->isInAgentHomeFolder($agent, $file)) {
            return true;
        }

        // Check if the file is within any explicitly allowed folder
        foreach ($allowedIds as $folderId) {
            if ($this->isDescendantOf($file, $folderId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a file is within an agent's home folder (/agents/{slug}/).
     */
    private function isInAgentHomeFolder(User $agent, WorkspaceFile $file): bool
    {
        $slug = Str::slug($agent->name);
        $current = $file;

        // Walk up the tree looking for the agent's home folder pattern
        while ($current) {
            if ($current->parent_id === null) {
                // We're at a root folder
                return false;
            }

            $parent = WorkspaceFile::find($current->parent_id);
            if (! $parent) {
                return false;
            }

            // Check if parent is the "agents" folder and current is the agent's slug folder
            if ($parent->name === 'agents' && $parent->parent_id === null && $current->name === $slug) {
                return true;
            }

            // Check if we're inside the agent's slug folder (deeper descendant)
            if ($current->name === $slug && $parent->name === 'agents' && $parent->parent_id === null) {
                return true;
            }

            $current = $parent;
        }

        return false;
    }

    /**
     * Check if a file is a descendant of a given folder.
     */
    private function isDescendantOf(WorkspaceFile $file, string $folderId): bool
    {
        if ($file->id === $folderId) {
            return true;
        }

        $current = $file;
        // Guard against corrupted parent chains creating infinite loops.
        $maxDepth = 20;

        while ($current->parent_id && $maxDepth-- > 0) {
            if ($current->parent_id === $folderId) {
                return true;
            }
            $current = WorkspaceFile::find($current->parent_id);
            if (! $current) {
                return false;
            }
        }

        return false;
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

    /**
     * @return array<int, object>
     */
    private function integrationProviders(): array
    {
        $registryClass = ToolProviderRegistry::class;

        if (! class_exists($registryClass) || ! app()->bound($registryClass)) {
            return [];
        }

        return app($registryClass)->all();
    }
}
