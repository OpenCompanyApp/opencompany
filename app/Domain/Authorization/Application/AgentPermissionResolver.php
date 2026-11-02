<?php

namespace App\Domain\Authorization\Application;

use App\Agents\Tools\ToolRegistry;
use App\Domain\Authorization\Domain\PermissionDecision;
use App\Models\AgentPermission;
use App\Models\ApprovalRequest;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Support\Str;
use App\Agents\Tools\External\ToolProviderRegistry;

/**
 * Resolves the effective permissions for one agent.
 *
 * This domain service owns OpenCompany's app-local authorization compatibility
 * rules. It combines persisted atomic permission rows, workspace-level
 * integration enablement, scoped policy grants, the approval-by-default policy,
 * manager hierarchy, file-tree grants, and access-request creation. The final
 * OpenCompany policy is evaluated here (agent behaviour modes were deleted).
 */
class AgentPermissionResolver
{
    /**
     * Tools that should never require approval regardless of policy defaults or DB settings.
     *
     * These are system/control-flow tools where requiring approval would make
     * the runtime unable to wait for or record approval decisions.
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

    /**
     * Built-in tools that mutate external systems or widen the workspace's
     * external exposure/trust surface. They require approval by default in
     * every agent (see resolveToolPermission()).
     *
     * - MCP server management: adds/changes/removes remote tool providers.
     * - Integration config/webhooks/identity links: change credentials,
     *   register callbacks at third parties, or make an external identity a
     *   trusted workspace member (which also removes untrusted-content labels).
     * - External channel join/leave: act on a third-party chat platform.
     *
     * Keep this list to built-ins only; package and MCP tools are classified
     * from provider metadata in isExternalMutation().
     */
    private const EXTERNAL_EXPOSURE_TOOLS = [
        'add_mcp_server',
        'update_mcp_server',
        'remove_mcp_server',
        'update_integration_config',
        'setup_integration_webhook',
        'link_external_user',
        'update_agent_integration_access',
        'join_external_channel',
        'leave_external_channel',
        // Widening another agent's grants could launder an admin opt-out
        // from the floor; it needs a human decision every time.
        'update_agent_tool_permissions',
    ];

    /**
     * Resolve the final permission for a tool.
     *
     * Priority: explicit deny, exempt system tools, scoped policy grant,
     * explicit per-agent admin rule, then the default (reads run; everything
     * else, including every external mutation, needs approval).
     *
     * Every agent-facing path reaches this method: direct Laravel AI tools and
     * Code Mode callbacks (ToolRegistry::evaluateToolPermission ->
     * OpenCompanyPermissionEvaluator -> ToolPermissionCheck) and MCP calls
     * (McpPermissionEvaluator), so the floor applies identically everywhere.
     *
     * @return array{allowed: bool, requires_approval: bool}
     */
    public function resolveToolPermission(User $agent, string $toolSlug, string $toolType): array
    {
        $permission = AgentPermission::forAgent($agent->id)
            ->tools()
            ->where('scope_key', $toolSlug)
            ->first();

        if ($permission && $permission->permission === 'deny') {
            return PermissionDecision::deny()->toToolArray();
        }

        if (in_array($toolSlug, self::APPROVAL_EXEMPT_TOOLS, true)) {
            return PermissionDecision::allow()->toToolArray();
        }

        // Policy model (Product Direction pillar 5; behaviour modes deleted).
        // Invariant order — do not reorder: explicit deny (above) > exempt
        // system tools (above) > scoped admin grant > per-agent admin rule >
        // default. Only an admin grant or an admin rule can let a write or an
        // external action run without approval; nothing the agent or a
        // trigger supplies can.
        if (app(PolicyGrantService::class)->covers($agent, $toolSlug)) {
            return PermissionDecision::allow()->toToolArray();
        }

        // Per-agent admin rule from the capabilities UI (scope tool, allow):
        // requires_approval=false is an `always` exception for that tool. Also
        // written by update_agent_tool_permissions, which is itself
        // approval-gated and refuses to waive approval the grantor needs.
        if ($permission) {
            return PermissionDecision::allow($permission->requires_approval)->toToolArray();
        }

        // Default: only a declared read runs without approval. Writes,
        // `action`, unknown types and every external mutation (MCP, external
        // sends, exposure-widening tools) need approval — a trigger is never
        // consent, and untyped tools must not slip past the policy.
        $requiresApproval = $toolType !== 'read' || $this->isExternalMutation($toolSlug);

        return PermissionDecision::allow($requiresApproval)->toToolArray();
    }

    /**
     * Whether a tool mutates an external system or widens external exposure.
     *
     * Classification rule (metadata-driven, no provider IDs hardcoded):
     * 1. MCP tools (`mcp_{server}__{tool}`): always, regardless of declared
     *    type. Remote servers self-describe their tools and OpenCompany has no
     *    trusted read-only marking for them, so unknown is treated as write.
     * 2. Built-ins listed in EXTERNAL_EXPOSURE_TOOLS.
     *
     * Not covered here: send_channel_message, whose externality depends on the
     * destination channel; see requiresExternalChannelSendApproval().
     */
    public function isExternalMutation(string $toolSlug): bool
    {
        if (preg_match('/^mcp_[a-z0-9_]+__.+$/', $toolSlug) === 1) {
            return true;
        }

        if (in_array($toolSlug, self::EXTERNAL_EXPOSURE_TOOLS, true)) {
            return true;
        }

        // With the integration package system deleted, MCP servers are the
        // only external tool providers and are matched by slug above.
        return false;
    }

    /**
     * Approval decision for delivering a message to a non-OpenCompany
     * destination (an `external` channel bridged to Telegram, Slack, ...).
     *
     * Hook for send-style tools whose externality is only known per call. The
     * same opt-out rule as resolveToolPermission() applies: an explicit allow
     * row for $toolSlug with requires_approval=false waives it; anything else
     * (no row, or a row requiring approval) requires approval in every mode.
     */
    public function requiresExternalChannelSendApproval(User $agent, string $toolSlug = 'send_channel_message'): bool
    {
        $permission = AgentPermission::forAgent($agent->id)
            ->tools()
            ->where('scope_key', $toolSlug)
            ->first();

        return ! ($permission && $permission->permission === 'allow' && ! $permission->requires_approval);
    }

    /**
     * @return array{allowed: bool, can_request: bool}
     */
    public function canAccessChannel(User $agent, string $channelId): array
    {
        $channelPerms = AgentPermission::forAgent($agent->id)
            ->channels()
            ->allowed()
            ->pluck('scope_key');

        if ($channelPerms->isEmpty()) {
            return PermissionDecision::allow()->toAccessArray();
        }

        if ($channelPerms->contains($channelId)) {
            return PermissionDecision::allow()->toAccessArray();
        }

        return PermissionDecision::deny(canRequest: true)->toAccessArray();
    }

    /**
     * @return array<int, string>|null
     */
    public function getAllowedChannelIds(User $agent): ?array
    {
        $channelPerms = AgentPermission::forAgent($agent->id)
            ->channels()
            ->allowed()
            ->pluck('scope_key');

        return $channelPerms->isEmpty() ? null : $channelPerms->toArray();
    }

    /**
     * @return array<int, string>|null
     */
    public function getAllowedFolderIds(User $agent): ?array
    {
        $folderPerms = AgentPermission::forAgent($agent->id)
            ->folders()
            ->allowed()
            ->pluck('scope_key');

        return $folderPerms->isEmpty() ? null : $folderPerms->toArray();
    }

    /**
     * @return string[]
     */
    public function getEnabledIntegrations(User $agent): array
    {
        $integrationPerms = AgentPermission::forAgent($agent->id)
            ->where('scope_type', 'integration')
            ->get();

        $allApps = ToolRegistry::INTEGRATION_APPS;
        foreach ($this->integrationProviders() as $provider) {
            if ($provider->isIntegration() && ! in_array($provider->appName(), $allApps, true)) {
                $allApps[] = $provider->appName();
            }
        }

        if (app()->bound('currentWorkspace')) {
            $workspaceEnabledIds = IntegrationSetting::forWorkspace()
                ->where('enabled', true)
                ->pluck('integration_id')
                ->toArray();

            $allApps = array_values(array_filter($allApps, function (string $app) use ($workspaceEnabledIds): bool {
                return str_starts_with($app, 'mcp_') || in_array($app, $workspaceEnabledIds, true);
            }));
        }

        if ($integrationPerms->isEmpty()) {
            return $allApps;
        }

        $denied = $integrationPerms
            ->where('permission', 'deny')
            ->pluck('scope_key')
            ->toArray();

        return array_values(array_filter($allApps, fn (string $app): bool => ! in_array($app, $denied, true)));
    }

    public function isIntegrationEnabled(User $agent, string $appName): bool
    {
        return in_array($appName, $this->getEnabledIntegrations($agent), true);
    }

    /**
     * @return array{allowed: bool, requires_approval: bool, can_request: bool}
     */
    public function canContactAgent(User $caller, User $target): array
    {
        if ($target->id === $caller->manager_id || $caller->id === $target->manager_id) {
            return PermissionDecision::allow()->toContactArray();
        }

        $explicit = AgentPermission::forAgent($caller->id)
            ->where('scope_type', 'agent')
            ->where('scope_key', $target->id)
            ->first();

        if ($explicit) {
            if ($explicit->permission === 'deny') {
                return PermissionDecision::deny(canRequest: true)->toContactArray();
            }

            return PermissionDecision::allow($explicit->requires_approval)->toContactArray();
        }

        $wildcard = AgentPermission::forAgent($caller->id)
            ->where('scope_type', 'agent')
            ->where('scope_key', '*')
            ->first();

        if ($wildcard) {
            if ($wildcard->permission === 'deny') {
                return PermissionDecision::deny(canRequest: true)->toContactArray();
            }

            return PermissionDecision::allow($wildcard->requires_approval)->toContactArray();
        }

        // Default for peers outside the manager chain: contacting creates work
        // for another agent (a write), so it needs approval unless an admin
        // rule above says otherwise.
        return PermissionDecision::allow(true)->toContactArray();
    }

    /**
     * @return array<int, string>|null
     */
    public function getAllowedFileFolderIds(User $agent): ?array
    {
        $perms = AgentPermission::forAgent($agent->id)
            ->where('scope_type', 'file_folder')
            ->allowed()
            ->pluck('scope_key');

        if ($perms->contains('*')) {
            return null;
        }

        return $perms->toArray();
    }

    public function canAccessFilePath(User $agent, WorkspaceFile $file): bool
    {
        $allowedIds = $this->getAllowedFileFolderIds($agent);

        if ($allowedIds === null) {
            return true;
        }

        if ($this->isInAgentHomeFolder($agent, $file)) {
            return true;
        }

        foreach ($allowedIds as $folderId) {
            if ($this->isDescendantOf($file, $folderId)) {
                return true;
            }
        }

        return false;
    }

    public function createAccessRequest(
        User $agent,
        string $scopeType,
        string $scopeKey,
        string $description,
    ): ApprovalRequest {
        return ApprovalRequest::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $agent->workspace_id,
            'type' => 'access',
            'title' => "Access request: {$scopeType} - {$scopeKey}",
            'description' => $description,
            'requester_id' => $agent->id,
            'status' => 'pending',
            'tool_execution_context' => [
                'scope_type' => $scopeType,
                'scope_key' => $scopeKey,
            ],
        ]);
    }

    private function isInAgentHomeFolder(User $agent, WorkspaceFile $file): bool
    {
        $slug = Str::slug($agent->name);
        $current = $file;

        while ($current) {
            if ($current->parent_id === null) {
                return false;
            }

            $parent = WorkspaceFile::find($current->parent_id);
            if (! $parent) {
                return false;
            }

            if ($parent->name === 'agents' && $parent->parent_id === null && $current->name === $slug) {
                return true;
            }

            if ($current->name === $slug && $parent->name === 'agents' && $parent->parent_id === null) {
                return true;
            }

            $current = $parent;
        }

        return false;
    }

    private function isDescendantOf(WorkspaceFile $file, string $folderId): bool
    {
        if ($file->id === $folderId) {
            return true;
        }

        $current = $file;
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
     * @return array<int, object>
     */
    private function integrationProviders(): array
    {
        return array_values(app(ToolProviderRegistry::class)->all());
    }
}
