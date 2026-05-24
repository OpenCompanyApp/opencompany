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
     * Resolve the final permission for a tool.
     *
     * Priority: explicit deny, exempt system tools, explicit allow, then
     * behavior-mode default.
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

        if ($permission) {
            return PermissionDecision::allow($permission->requires_approval)->toToolArray();
        }

        return PermissionDecision::allow($this->behaviorModeRequiresApproval($agent, $toolType))->toToolArray();
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
        return match ($agent->behavior_mode ?? 'autonomous') {
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
        if (! class_exists(ToolProviderRegistry::class) || ! app()->bound(ToolProviderRegistry::class)) {
            return [];
        }

        return app(ToolProviderRegistry::class)->all();
    }
}
