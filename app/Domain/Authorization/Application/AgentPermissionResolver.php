<?php

namespace App\Domain\Authorization\Application;

use App\Agents\Runtime\ActiveAgentRun;
use App\Agents\Tools\External\ToolProviderRegistry;
use App\Agents\Tools\ToolRegistry;
use App\Domain\Authorization\Domain\PermissionDecision;
use App\Domain\Authorization\Engine\Decision;
use App\Domain\Authorization\Engine\PolicyEngine;
use App\Domain\Authorization\Policy\ActionClassifier;
use App\Models\AgentPermission;
use App\Models\AgentRunRecord;
use App\Models\ApprovalRequest;
use App\Models\Channel;
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
 *
 * Since GIN-1197 the tool gate, channel access and agent-to-agent contact
 * are decided by the central PolicyEngine (through AuthorizationService or
 * directly for the hot tool path); this class gathers their facts and keeps
 * the array shapes existing callers consume. Do not re-add rule logic here.
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
        private ?PolicyEngine $engine = null,
    ) {
        $this->classifier ??= new ActionClassifier;
        $this->engine ??= new PolicyEngine;
    }

    /**
     * Resolve the final permission for a tool.
     *
     * Reversibility, not "write", decides the gate (Product Direction pillar
     * 5; docs/architecture/governance-model.md "Decision order"). The rule
     * order now lives in PolicyEngine (`tool.invoke`): explicit per-agent
     * deny → hard-deny floor → control-flow exempt tools → on-behalf-of
     * intersection → trigger-started external actions always ask → scoped
     * grants (`always` never for purge/exposure/structural) → per-agent admin
     * rule → preset default gate. This method only gathers the facts.
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
        $decision = $this->decideTool($agent, $toolSlug, $toolType);

        return $decision->denied()
            ? PermissionDecision::deny()->toToolArray()
            : PermissionDecision::allow($decision->requiresApproval())->toToolArray();
    }

    /**
     * The engine decision (with reason code and trace) for one tool call.
     * Never records to the ledger: tools are evaluated at registration time
     * for every tool, and executions have their own tool ledger.
     */
    public function decideTool(User $agent, string $toolSlug, string $toolType): Decision
    {
        $class = $this->actionClass($toolSlug, $toolType);

        $permission = AgentPermission::forAgent($agent->id)
            ->tools()
            ->where('scope_key', $toolSlug)
            ->first();

        $explicit = $permission !== null
            ? ['permission' => (string) $permission->permission, 'requires_approval' => (bool) $permission->requires_approval]
            : null;
        $exempt = in_array($toolSlug, self::APPROVAL_EXEMPT_TOOLS, true);

        // Facts the engine never reaches (explicit deny, floor, exempt) are
        // not loaded: grant and run lookups run once per tool per run.
        $settled = ($explicit['permission'] ?? null) === 'deny' || $class === ActionClassifier::FLOOR || $exempt;
        $grants = app(PolicyGrantService::class);
        $grant = $settled ? ['scoped' => false, 'always' => false] : [
            'scoped' => $grants->covers($agent, $toolSlug, includeAlways: false),
            'always' => $grants->covers($agent, $toolSlug, includeAlways: true),
        ];
        $external = in_array($class, ActionClassifier::EXTERNAL_CLASSES, true);

        $workspace = $agent->workspace_id !== null ? Workspace::query()->find($agent->workspace_id) : null;
        // Legacy agents without a workspace keep the previous behaviour (the
        // `team` preset, no boundary to cross); every real agent has one.
        $workspaceId = $workspace !== null ? (string) $workspace->id : 'agent-without-workspace:'.$agent->id;

        $actors = [(string) $agent->id => [
            'kind' => 'agent',
            'workspace_id' => $workspaceId,
            'manager_id' => $agent->manager_id,
        ]];
        $principalId = $settled ? null : app(OnBehalfOf::class)->currentPrincipalId();
        if ($principalId !== null) {
            $principal = User::query()->find($principalId);
            if ($principal !== null && $principal->type !== 'agent' && $workspace !== null) {
                $actors[$principalId] = app(AuthorizationFacts::class)->actor($principal, $workspace);
            }
        }

        return $this->engine->decide(
            ['workspace' => $workspaceId, 'actor' => (string) $agent->id, 'on_behalf_of' => $principalId, 'action' => 'tool.invoke'],
            [
                'now' => now()->utc()->toIso8601String(),
                'workspace' => [
                    'id' => $workspaceId,
                    'owner_id' => $workspace?->owner_id,
                    'preset' => $workspace?->effectiveGovernancePreset() ?? 'team',
                ],
                'actors' => $actors,
                'resource' => null,
                'context' => [
                    'tool' => $toolSlug,
                    'action_class' => $class,
                    'explicit_permission' => $explicit,
                    'approval_exempt' => $exempt,
                    'trigger_started' => ! $settled && $external && $this->currentRunIsTriggerStarted(),
                    'grant' => $grant,
                ],
            ],
        );
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
     * Whether the agent may read a channel, decided by PolicyEngine
     * (`channel.read`) with the run's on-behalf-of human: the agent's channel
     * grants, membership for DMs and private channels, intersected with what
     * the human may read. A channel id that does not exist in the agent's
     * workspace falls back to the grant list only (callers report "not
     * found" themselves).
     *
     * @return array{allowed: bool, can_request: bool}
     */
    public function canAccessChannel(User $agent, string $channelId): array
    {
        $channel = $agent->workspace_id !== null
            ? Channel::query()->where('workspace_id', $agent->workspace_id)->find($channelId)
            : null;

        if ($channel === null) {
            $grants = $this->getAllowedChannelIds($agent);

            return $grants === null || in_array($channelId, $grants, true)
                ? PermissionDecision::allow()->toAccessArray()
                : PermissionDecision::deny(canRequest: true)->toAccessArray();
        }

        $workspace = Workspace::query()->find($agent->workspace_id);
        $principalId = app(OnBehalfOf::class)->currentPrincipalId();
        $principal = $principalId !== null ? User::query()->find($principalId) : null;

        $decision = app(AuthorizationService::class)->decide(
            $agent,
            'channel.read',
            $channel,
            onBehalfOf: $principal,
            workspace: $workspace,
        );
        if ($decision->allowed()) {
            return PermissionDecision::allow()->toAccessArray();
        }

        // Grant and membership denials are requestable from an admin; a
        // denial for the on-behalf-of human is not the agent's to request.
        return PermissionDecision::deny(
            canRequest: in_array($decision->reasonCode, ['agent_channel_not_granted', 'channel_not_member'], true),
        )->toAccessArray();
    }

    /**
     * The agent's raw channel grant list (null = unrestricted). This is the
     * stored grant, not visibility: list channels with
     * AuthorizationScopes::readableChannels() instead.
     *
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
     * Document folder ids explicitly denied to this agent (scope `folder`,
     * permission `deny`). A deny covers the folder and every descendant and
     * wins over any allow (governance decision order: explicit deny before
     * grants). Empty when there are none.
     *
     * @return list<string>
     */
    public function getDeniedFolderIds(User $agent): array
    {
        return AgentPermission::forAgent($agent->id)
            ->folders()
            ->denied()
            ->pluck('scope_key')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
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
     * Agent-to-agent delegation gate, decided by PolicyEngine
     * (`agent.delegate`): the manager chain may always talk; admin contact
     * rules (specific target, then `*`) allow, ask or deny; other peers ask.
     * The workspace boundary and the run's on-behalf-of human apply.
     *
     * @return array{allowed: bool, requires_approval: bool, can_request: bool}
     */
    public function canContactAgent(User $caller, User $target): array
    {
        $workspace = $caller->workspace_id !== null ? Workspace::query()->find($caller->workspace_id) : null;
        $principalId = app(OnBehalfOf::class)->currentPrincipalId();
        $principal = $principalId !== null ? User::query()->find($principalId) : null;

        $decision = app(AuthorizationService::class)->decide(
            $caller,
            'agent.delegate',
            $target->type === 'agent' ? $target : ['type' => 'human', 'id' => (string) $target->id, 'workspace_id' => null],
            onBehalfOf: $principal?->type === 'agent' ? null : $principal,
            workspace: $workspace,
        );

        if ($decision->denied()) {
            return PermissionDecision::deny(canRequest: $decision->reasonCode === 'agent_contact_denied')->toContactArray();
        }

        return PermissionDecision::allow($decision->requiresApproval())->toContactArray();
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
