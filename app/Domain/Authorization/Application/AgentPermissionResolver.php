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
use OpenCompany\IntegrationCore\Support\ToolProviderRegistry;

/**
 * Resolves the effective permissions for one agent.
 *
 * This domain service owns OpenCompany's app-local authorization compatibility
 * rules. It combines persisted atomic permission rows, workspace-level
 * integration enablement, behavior-mode defaults, manager hierarchy, file-tree
 * grants, and access-request creation. Package metadata may contribute tool and
 * integration catalogs, but the final OpenCompany policy is evaluated here.
 */
class AgentPermissionResolver
{
    /**
     * Tools that should never require approval regardless of behavior mode or DB settings.
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
     * every behavior mode (see resolveToolPermission()).
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
     * Process-wide slug index for package integration tools.
     *
     * @var array{key: string, tools: array<string, bool>}|null
     */
    private static ?array $externalToolIndex = null;

    /**
     * Resolve the final permission for a tool.
     *
     * Priority: explicit deny, exempt system tools, explicit per-agent row,
     * external-mutation approval floor, then behavior-mode default.
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

        // Invariant: an explicit per-agent tool row (scope tool, permission
        // allow) is the ONLY way to opt a tool out of the external approval
        // floor below. Such rows are written by workspace admins in the
        // capabilities UI, or by another agent via update_agent_tool_permissions,
        // which refuses to waive approval the granting agent itself requires.
        // Keep this check above the floor; keep the floor above behavior mode.
        if ($permission) {
            return PermissionDecision::allow($permission->requires_approval)->toToolArray();
        }

        // Approval floor (product direction: triggers are never consent).
        // Tools that mutate external systems or widen exposure need approval
        // even for `autonomous` agents, because a run started by a schedule,
        // webhook or inbound message may be acting on injected instructions.
        // Internal workspace writes (tasks, docs, tables, VFS, ...) are not
        // matched here and keep following the behavior mode.
        if ($this->isExternalMutation($toolSlug)) {
            return PermissionDecision::allow(true)->toToolArray();
        }

        return PermissionDecision::allow($this->behaviorModeRequiresApproval($agent, $toolType))->toToolArray();
    }

    /**
     * Whether a tool mutates an external system or widens external exposure.
     *
     * Classification rule (metadata-driven, no provider IDs hardcoded):
     * 1. MCP tools (`mcp_{server}__{tool}`): always, regardless of declared
     *    type. Remote servers self-describe their tools and OpenCompany has no
     *    trusted read-only marking for them, so unknown is treated as write.
     * 2. Package integration tools: the slug belongs to a ToolProviderRegistry
     *    provider with isIntegration() === true AND credentialFields() !== []
     *    (it acts on a third-party account), and the provider metadata type is
     *    not exactly `read` (missing/custom types fail closed as writes).
     *    Credential-less integrations (local renderers such as diagram/PDF
     *    packages) only write workspace files, so they follow behavior mode.
     * 3. Built-ins listed in EXTERNAL_EXPOSURE_TOOLS.
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

        // The provider's declared type is authoritative for package tools.
        // The caller-supplied tool type is intentionally not consulted: some
        // callers pass 'write' as a "strictest" placeholder
        // (update_agent_tool_permissions), which would gate genuine reads.
        return $this->externalToolIndex()[$toolSlug] ?? false;
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
     * Map package integration tool slugs to "is a write" for credentialed
     * integration providers. Cached per process and rebuilt when the provider
     * set changes (MCP servers register providers at runtime).
     *
     * @return array<string, bool>
     */
    private function externalToolIndex(): array
    {
        $providers = $this->integrationProviders();
        $key = md5(implode('|', array_map(
            fn (object $provider): string => spl_object_id($provider).':'.$provider->appName(),
            $providers,
        )));

        if (self::$externalToolIndex !== null && self::$externalToolIndex['key'] === $key) {
            return self::$externalToolIndex['tools'];
        }

        $tools = [];
        foreach ($providers as $provider) {
            if (! $provider->isIntegration() || str_starts_with($provider->appName(), 'mcp_')) {
                continue;
            }

            if (! method_exists($provider, 'credentialFields') || $provider->credentialFields() === []) {
                continue;
            }

            foreach ($provider->tools() as $slug => $meta) {
                $type = is_array($meta) ? ($meta['type'] ?? null) : null;
                $tools[(string) $slug] = $type !== 'read';
            }
        }

        self::$externalToolIndex = ['key' => $key, 'tools' => $tools];

        return $tools;
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

        return PermissionDecision::allow($this->behaviorModeRequiresApproval($caller, 'write'))->toContactArray();
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

    private function behaviorModeRequiresApproval(User $agent, string $toolType): bool
    {
        // Invariant: `supervised` gates everything that is not a declared read.
        // Tools with a missing or non-standard type (the registry defaults
        // unknown metadata to 'action') are treated as mutations, never as
        // reads — an untyped tool must not slip past supervision.
        return match ($agent->behavior_mode ?? 'autonomous') {
            'strict' => true,
            'supervised' => $toolType !== 'read',
            default => false,
        };
    }

    /**
     * @return array<int, object>
     */
    private function integrationProviders(): array
    {
        if (! class_exists(ToolProviderRegistry::class) || ! app()->bound(ToolProviderRegistry::class)) {
            return [];
        }

        return app(ToolProviderRegistry::class)->all();
    }
}
