<?php

namespace App\Domain\Authorization\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\External\ToolProviderRegistry;
use App\Agents\Tools\ToolRegistry;
use App\Domain\Authorization\Domain\PermissionDecision;
use App\Domain\Authorization\Policy\ActionClassifier;
use App\Domain\Authorization\Policy\PresetGates;
use App\Models\AgentPermission;
use App\Models\AgentRunRecord;
use App\Models\ApprovalRequest;
use App\Models\IntegrationSetting;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use Illuminate\Support\Str;

/**
 * Resolves the effective permissions for one agent.
 *
 * This domain service owns OpenCompany's app-local authorization compatibility
 * rules. It combines persisted atomic permission rows, workspace-level
 * integration enablement, scoped policy grants, the reversibility-based
 * governance gate (action class x workspace preset), manager hierarchy,
 * file-tree grants, and access-request creation. Agent behaviour modes were
 * deleted; presets set defaults only and never relax the invariants here.
 */
class AgentPermissionResolver
{
    /**
     * Control-flow tools that never require approval: requiring approval would
     * leave the runtime unable to wait for or record approval decisions. Task
     * bookkeeping is a reversible edit and is allowed by every preset anyway;
     * contact_agent has its own relationship gate (canContactAgent()).
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

    public function __construct(
        private ?ActionClassifier $classifier = null,
        private ?PresetGates $gates = null,
    ) {
        $this->classifier ??= new ActionClassifier;
        $this->gates ??= new PresetGates;
    }

    /**
     * Resolve the final permission for a tool.
     *
     * Reversibility, not "write", decides the gate (Product Direction pillar
     * 5; docs/architecture/governance-model.md "Decision order"). Invariant
     * order — do not reorder, and never let a preset or exception run before
     * an invariant:
     *
     * 1. explicit per-agent deny;
     * 2. hard-deny floor (never relaxed by anything);
     * 3. control-flow exempt tools;
 * 3b. on-behalf-of intersection (OnBehalfOf): the run's human principal must
 *    still have access, and governance/exposure actions need an admin one;
     * 4. trigger-started runs: external classes always ask, even with an
     *    exception (a trigger is never consent);
     * 5. exact-match exceptions: scoped policy grants, then the per-agent admin
     *    rule. `always` is never honoured for purge/exposure/structural;
     * 6. preset default gate for the action class.
     *
     * On-behalf-of intersection runs separately (OnBehalfOfCheck) because it
     * needs the call's target, not just the tool.
     *
     * Every agent-facing path reaches this method: direct Laravel AI tools and
     * Code Mode callbacks (ToolRegistry::evaluateToolPermission ->
     * OpenCompanyPermissionEvaluator -> ToolPermissionCheck), VFS mutations
     * (VfsMutationPolicy) and MCP calls (McpPermissionEvaluator).
     *
     * @param  string  $toolType  declared tool type (`read`, `write`, ...); only a
     *                            built-in `read` can classify as read
     * @return array{allowed: bool, requires_approval: bool}
     */
    public function resolveToolPermission(User $agent, string $toolSlug, string $toolType): array
    {
        $class = $this->actionClass($toolSlug, $toolType);

        $permission = AgentPermission::forAgent($agent->id)
            ->tools()
            ->where('scope_key', $toolSlug)
            ->first();

        if ($permission && $permission->permission === 'deny') {
            return PermissionDecision::deny()->toToolArray();
        }

        // Invariant: the floor is denied before any exemption, exception,
        // admin rule or preset is consulted.
        if ($class === ActionClassifier::FLOOR) {
            return PermissionDecision::deny()->toToolArray();
        }

        if (in_array($toolSlug, self::APPROVAL_EXEMPT_TOOLS, true)) {
            return PermissionDecision::allow()->toToolArray();
        }

        // Invariant: on-behalf-of intersection before any exception or
        // preset can allow — an agent never exceeds the human it acts for.
        if (app(OnBehalfOf::class)->denialFor($agent, $class) !== null) {
            return PermissionDecision::deny()->toToolArray();
        }

        $external = in_array($class, ActionClassifier::EXTERNAL_CLASSES, true);
        if ($external && $this->currentRunIsTriggerStarted()) {
            return PermissionDecision::allow(true)->toToolArray();
        }

        $alwaysAllowed = ! in_array($class, PresetGates::NO_ALWAYS_EXCEPTION, true);
        if (app(PolicyGrantService::class)->covers($agent, $toolSlug, includeAlways: $alwaysAllowed)) {
            return PermissionDecision::allow()->toToolArray();
        }

        $gate = $this->gates->gate($class, $this->presetFor($agent));

        // Per-agent admin rule from the capabilities UI (scope tool, allow).
        // requires_approval=true makes a tool stricter than the preset;
        // requires_approval=false is a standing ("always") exception, which
        // the classes in NO_ALWAYS_EXCEPTION never accept.
        if ($permission) {
            if ($permission->requires_approval) {
                return PermissionDecision::allow(true)->toToolArray();
            }
            if ($alwaysAllowed) {
                return PermissionDecision::allow()->toToolArray();
            }
        }

        if ($gate === PresetGates::DENY) {
            return PermissionDecision::deny()->toToolArray();
        }

        return PermissionDecision::allow($gate === PresetGates::APPROVAL)->toToolArray();
    }

    /**
     * Governance action class of a tool (see ActionClassifier). Callers that
     * know richer metadata may classify directly; this keeps the declared
     * tool type as the only read signal.
     */
    public function actionClass(string $toolSlug, string $toolType = 'write'): string
    {
        return $this->classifier->classify($toolSlug, ['type' => $toolType]);
    }

    /**
     * Whether a tool leaves the workspace or widens its exposure
     * (external_send, spend, exposure_widening). MCP tools and unclassified
     * tools always are. send_channel_message is classified internal; its
     * external destinations are gated per call by
     * requiresExternalChannelSendApproval().
     */
    public function isExternalMutation(string $toolSlug): bool
    {
        return in_array($this->actionClass($toolSlug), ActionClassifier::EXTERNAL_CLASSES, true);
    }

    /**
     * Effective preset for the agent's workspace; `team` when the workspace
     * cannot be loaded (the stricter multi-member default).
     */
    private function presetFor(User $agent): string
    {
        $workspace = $agent->workspace_id !== null ? Workspace::query()->find($agent->workspace_id) : null;

        return $workspace?->effectiveGovernancePreset() ?? 'team';
    }

    /**
     * Whether the ambient run was started by a trigger (a routine schedule or
     * event) rather than a human. Routine runs and tasks sourced from a
     * routine count; delegations inherit through their task source.
     */
    private function currentRunIsTriggerStarted(): bool
    {
        if (! app()->bound(ActiveAgentRun::class)) {
            return false;
        }

        try {
            $record = app(ActiveAgentRun::class)->record();
        } catch (\Throwable) {
            return false;
        }

        if (in_array($record->kind, [AgentRunRecord::KIND_ROUTINE_AGENT, AgentRunRecord::KIND_ROUTINE_SCRIPT], true)) {
            return true;
        }

        return $record->task?->source === Task::SOURCE_ROUTINE;
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
