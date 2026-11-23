<?php

namespace App\Domain\Authorization\Engine;

use App\Domain\Authorization\Policy\ActionClassifier as C;
use App\Domain\Authorization\Policy\PresetGates;

/**
 * The one place that answers "may X do Y to Z?" (GIN-1197; ROADMAP Phase 0
 * P0 item 1; Product Direction pillar 5).
 *
 * Input: a request (workspace, actor, optional on-behalf-of human, action)
 * and plain facts (workspace, the actors involved, the resource, context).
 * Output: a Decision (allow, deny or approval_required) with a stable reason
 * code and a trace. Facts are loaded by AuthorizationFacts (Laravel side);
 * this class never touches the database, the container, config or the
 * clock, so it is deterministic and portable. The conformance vectors in
 * tests/conformance/authorization are run against it directly, and a later
 * Rust governance crate must pass the same vectors (GIN-2057).
 *
 * Owns: action specs, the fixed decision order below, the always-on
 * invariants it can check, the human role capability rules, the agent tool
 * gate (ported from AgentPermissionResolver, which now delegates here), the
 * agent-to-agent delegation gate, approval resolution, channel membership,
 * document sharing rows, agent management, AI Gateway key scopes and expiry,
 * and settings command capabilities.
 *
 * Does not own: loading facts, recording the ledger, HTTP status mapping
 * (AuthorizationService), agent document folder grants and the VFS
 * trace wording (still AuthorizesVfsDocuments), or kill switches and budgets
 * (not built yet).
 *
 * Decision order (invariant — do not reorder; no later step may widen what
 * an earlier step denied):
 *   1. Known action (unknown actions fail closed).
 *   2. Workspace boundary: the request workspace matches the facts, the
 *      actor belongs to it, and the resource belongs to it.
 *   3. Hard-deny floor (an agent changing its own grants or manager).
 *   4. Actor kind: human-only actions refuse agents and API keys; an agent
 *      never approves anything.
 *   5. The action's own rule: role capabilities, relationships, membership,
 *      sharing rows, key scopes, tool gates.
 *   6. On-behalf-of intersection: an agent acting for a human never exceeds
 *      that human. The same request is decided for the human; a denial there
 *      denies the agent.
 * The effective decision is the most restrictive of the steps
 * (deny > approval_required > allow).
 */
final class PolicyEngine
{
    /**
     * Bump on any rule change. Recorded with every ledger entry together with
     * policyHash(), so an auditor can tell which rules produced a decision.
     */
    public const VERSION = '2026-11-23.1';

    /** Ledger modes per action: record every decision, only non-allows, or never. */
    public const AUDIT_ALWAYS = 'always';
    public const AUDIT_DENY = 'deny';
    public const AUDIT_NEVER = 'never';

    /** Settings commands and the capability each needs (ROADMAP P0 item 8). */
    public const SETTINGS_COMMANDS = [
        'pause_agents' => Capabilities::SETTINGS_OPERATE,
        'resume_agents' => Capabilities::SETTINGS_OPERATE,
        'reset_agents' => Capabilities::SETTINGS_OPERATE,
        'retry_failed_jobs' => Capabilities::SETTINGS_OPERATE,
        'flush_failed_jobs' => Capabilities::SETTINGS_OPERATE,
        'refresh_mcp_tools' => Capabilities::SETTINGS_OPERATE,
        'test_telegram' => Capabilities::SETTINGS_OPERATE,
        'reindex_documents' => Capabilities::SETTINGS_OPERATE,
        'clear_embedding_cache' => Capabilities::SETTINGS_OPERATE,
        'reindex_documents_fresh' => Capabilities::SETTINGS_DANGER,
        'reset_embeddings' => Capabilities::SETTINGS_DANGER,
        'reset_memory' => Capabilities::SETTINGS_DANGER,
        'clear_conversation_summaries' => Capabilities::SETTINGS_DANGER,
    ];

    /** AI Gateway operations a key may be scoped to. */
    public const GATEWAY_OPERATIONS = ['models.list', 'chat.completions', 'embeddings'];

    /**
     * Action specs: rule method, permitted actor kinds and ledger mode.
     *
     * @var array<string, array{rule: string, actors: list<string>, audit: string}>
     */
    private const ACTIONS = [
        'workspace.read' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_NEVER],
        'workspace.write' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'audit.read' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'policy.edit' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],

        'channel.create' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'channel.read' => ['rule' => 'channelReadRule', 'actors' => ['human', 'agent'], 'audit' => self::AUDIT_DENY],
        'channel.post' => ['rule' => 'channelPostRule', 'actors' => ['human', 'agent'], 'audit' => self::AUDIT_DENY],
        'channel.manage_members' => ['rule' => 'channelManageMembersRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'channel.join' => ['rule' => 'channelJoinRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'channel.leave' => ['rule' => 'channelLeaveRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'message.delete' => ['rule' => 'messageOwnedRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'message.reaction.remove' => ['rule' => 'messageOwnedRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],

        'document.read' => ['rule' => 'documentReadRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'document.write' => ['rule' => 'documentWriteRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'document.delete' => ['rule' => 'documentWriteRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'document.create' => ['rule' => 'documentCreateRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'document.comment' => ['rule' => 'documentCommentRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'document.comment.manage' => ['rule' => 'documentCommentManageRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],

        'agent.read' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_NEVER],
        'agent.create' => ['rule' => 'agentCreateRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'agent.update' => ['rule' => 'agentManagedRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'agent.update_identity' => ['rule' => 'agentManagedRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'agent.delete' => ['rule' => 'agentManagedRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'agent.reassign_manager' => ['rule' => 'agentAdminRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'agent.update_grants' => ['rule' => 'agentAdminRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'agent.delegate' => ['rule' => 'agentDelegateRule', 'actors' => ['agent'], 'audit' => self::AUDIT_DENY],
        'tool.invoke' => ['rule' => 'toolInvokeRule', 'actors' => ['agent'], 'audit' => self::AUDIT_NEVER],

        'approval.resolve' => ['rule' => 'approvalResolveRule', 'actors' => ['human', 'agent'], 'audit' => self::AUDIT_ALWAYS],

        'member.invite' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'member.update_role' => ['rule' => 'memberChangeRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'member.remove' => ['rule' => 'memberChangeRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],

        'settings.read' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],
        'settings.update' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'settings.diagnostics.platform' => ['rule' => 'platformOperatorRule', 'actors' => ['human'], 'audit' => self::AUDIT_DENY],

        'ai_gateway.manage' => ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS],
        'ai_gateway.invoke' => ['rule' => 'gatewayInvokeRule', 'actors' => ['api_key'], 'audit' => self::AUDIT_DENY],
    ];

    /** Capability needed by actions whose rule is a plain capability check. */
    private const ACTION_CAPABILITY = [
        'workspace.read' => Capabilities::WORKSPACE_READ,
        'workspace.write' => Capabilities::WORKSPACE_WRITE,
        'audit.read' => Capabilities::AUDIT_READ,
        'policy.edit' => Capabilities::POLICY_EDIT,
        'channel.create' => Capabilities::CHANNEL_CREATE,
        'agent.read' => Capabilities::WORKSPACE_READ,
        'member.invite' => Capabilities::MEMBER_MANAGE,
        'settings.read' => Capabilities::SETTINGS_READ,
        'settings.update' => Capabilities::SETTINGS_UPDATE,
        'ai_gateway.manage' => Capabilities::AI_GATEWAY_MANAGE,
    ];

    /** Channel types readable by every workspace member. */
    private const OPEN_CHANNEL_TYPES = ['public'];

    /** Tool classes only an admin principal may drive (OnBehalfOf rule). */
    private const ADMIN_PRINCIPAL_CLASSES = [C::GOVERNANCE, C::EXPOSURE_WIDENING];

    /**
     * Every action name the engine knows, settings commands included.
     *
     * @return list<string>
     */
    public static function actions(): array
    {
        $commands = array_map(fn (string $name): string => 'settings.command.'.$name, array_keys(self::SETTINGS_COMMANDS));

        return [...array_keys(self::ACTIONS), ...$commands];
    }

    /** Ledger mode for an action (unknown actions are always recorded). */
    public static function auditMode(string $action): string
    {
        if (str_starts_with($action, 'settings.command.')) {
            return self::AUDIT_ALWAYS;
        }

        return self::ACTIONS[$action]['audit'] ?? self::AUDIT_ALWAYS;
    }

    /**
     * Content hash of the rules that carry data (role table, action specs,
     * settings commands, preset gates) plus VERSION. Code rules are covered
     * by VERSION; this catches data-table edits that forgot to bump it.
     */
    public static function policyHash(): string
    {
        return hash('sha256', json_encode([
            self::VERSION, Capabilities::TABLE, self::ACTIONS, self::SETTINGS_COMMANDS, PresetGates::NO_ALWAYS_EXCEPTION,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Decide one request against facts.
     *
     * @param  array{workspace?: string|null, actor?: string|null, on_behalf_of?: string|null, action?: string|null}  $request
     * @param  array<string, mixed>  $facts  see tests/conformance/authorization/README.md
     */
    public function decide(array $request, array $facts): Decision
    {
        $trace = [];

        return $this->evaluate($request, $facts, $trace, true);
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function evaluate(array $request, array $facts, array &$trace, bool $withOnBehalfOf): Decision
    {
        $action = (string) ($request['action'] ?? '');
        $spec = $this->spec($action);

        // Step 1. Invariant: unknown actions fail closed.
        if ($spec === null) {
            return $this->deny($trace, 'unknown_action', "Unknown action \"{$action}\".");
        }
        $trace[] = "action:{$action}";

        $workspace = is_array($facts['workspace'] ?? null) ? $facts['workspace'] : [];
        $workspaceId = $request['workspace'] ?? null;
        $actorId = (string) ($request['actor'] ?? '');
        $actor = $facts['actors'][$actorId] ?? null;
        $resource = is_array($facts['resource'] ?? null) ? $facts['resource'] : null;
        $context = is_array($facts['context'] ?? null) ? $facts['context'] : [];

        if (! is_string($workspaceId) || $workspaceId === '' || ($workspace['id'] ?? null) !== $workspaceId) {
            return $this->deny($trace, 'workspace_unknown', 'The request names no workspace, or a different one than the facts.');
        }
        if (! is_array($actor) || $actorId === '') {
            return $this->deny($trace, 'actor_unknown', 'The actor is unknown.');
        }
        $kind = (string) ($actor['kind'] ?? '');

        // Approval resolution keeps its historical rule order and reason
        // codes (ApprovalResolutionPolicy delegates here), so it evaluates
        // its own boundary before the generic one.
        if ($spec['rule'] === 'approvalResolveRule') {
            return $this->approvalResolveRule($actorId, $actor, $workspace, $resource, $trace);
        }

        // Step 2. Invariant: workspace boundary before anything else.
        if (! $this->belongs($actorId, $actor, $workspace)) {
            return $this->deny($trace, 'actor_not_in_workspace', 'The actor does not belong to this workspace.');
        }
        if ($resource !== null && ($resource['workspace_id'] ?? null) !== $workspaceId) {
            return $this->deny($trace, 'workspace_boundary', 'The resource belongs to a different workspace.');
        }
        $trace[] = 'workspace_boundary:ok';

        // Step 3. Invariant: hard-deny floor before actor kind or any rule.
        if ($kind === 'agent' && in_array($action, ['agent.update_grants', 'agent.reassign_manager'], true)
            && ($resource['id'] ?? null) === $actorId) {
            return $this->deny($trace, 'floor_self_governance', 'An agent can never change its own grants or manager.');
        }

        // Step 4. Actor kind.
        if (! in_array($kind, $spec['actors'], true)) {
            return $kind === 'agent'
                ? $this->deny($trace, 'human_only_action', 'Only a human can do this; an agent acts through governed tools.')
                : $this->deny($trace, 'actor_kind_not_permitted', "A {$kind} actor cannot perform {$action}.");
        }
        $trace[] = "actor_kind:{$kind}";

        // Step 5. The action's own rule.
        $role = $this->roleOf($actorId, $actor, $workspace);
        $decision = $this->{$spec['rule']}($action, $actorId, $actor, $role, $workspace, $resource, $context, $request, $facts, $trace);

        // Step 6. Invariant: on-behalf-of intersection. A denial for the
        // human denies the agent; an allow never widens the agent's answer.
        $principalId = $request['on_behalf_of'] ?? null;
        if ($withOnBehalfOf && $kind === 'agent' && is_string($principalId) && $principalId !== '' && ! $decision->denied()
            && $spec['rule'] !== 'toolInvokeRule') {
            $principalDecision = $this->onBehalfOf($request, $facts, $principalId, $workspace, $trace);
            if ($principalDecision !== null) {
                return $principalDecision;
            }
        }

        return $decision;
    }

    /**
     * Decide the same request for the on-behalf-of human. Null when the
     * human may do it too; otherwise a denial naming the human.
     *
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $workspace
     * @param  list<string>  $trace
     */
    private function onBehalfOf(array $request, array $facts, string $principalId, array $workspace, array &$trace): ?Decision
    {
        $principal = $facts['actors'][$principalId] ?? null;
        $name = is_array($principal) ? (string) ($principal['name'] ?? $principalId) : $principalId;
        if (! is_array($principal) || ($principal['kind'] ?? null) !== 'human' || ! $this->belongs($principalId, $principal, $workspace)) {
            return $this->deny($trace, 'on_behalf_of_not_member', "Acting on behalf of {$name}, who is no longer a member of this workspace.");
        }

        // Agent-only actions (delegation) have no human equivalent: the
        // membership check above is the whole intersection.
        $spec = $this->spec((string) $request['action']);
        if ($spec === null || ! in_array('human', $spec['actors'], true)) {
            $trace[] = 'on_behalf_of:member';

            return null;
        }

        $principalTrace = [];
        $decision = $this->evaluate(
            ['workspace' => $request['workspace'], 'actor' => $principalId, 'on_behalf_of' => null, 'action' => $request['action']],
            $facts,
            $principalTrace,
            false,
        );
        if ($decision->denied()) {
            return $this->deny($trace, 'on_behalf_of_denied', "Acting on behalf of {$name}, who may not do this ({$decision->reasonCode}).");
        }
        $trace[] = 'on_behalf_of:ok';

        return null;
    }

    // ─── Rules ────────────────────────────────────────────────────────────
    // Every rule has the same signature so ACTIONS can name it. Each returns
    // the final decision for the actor (before on-behalf-of).

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function capabilityRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $capability = str_starts_with($action, 'settings.command.')
            ? self::SETTINGS_COMMANDS[substr($action, strlen('settings.command.'))]
            : self::ACTION_CAPABILITY[$action];

        return $this->requireCapability($role, $capability, $trace) ?? $this->allow($trace, 'capability', "Role {$role} holds {$capability}.");
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function platformOperatorRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        if (($actor['platform_operator'] ?? false) !== true) {
            return $this->deny($trace, 'platform_operator_required', 'Platform logs and operations need a platform operator; workspace administration is not enough.');
        }

        return $this->allow($trace, 'platform_operator', 'The actor is a platform operator.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function channelReadRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $channel = $this->expectResource($resource, 'channel');
        if ($channel === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a channel.');
        }

        return $this->channelReadable($actorId, $actor, $role, $channel, $trace)
            ?? $this->allow($trace, 'channel_readable', 'The actor can read this channel.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function channelPostRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $channel = $this->expectResource($resource, 'channel');
        if ($channel === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a channel.');
        }
        if (($denied = $this->channelReadable($actorId, $actor, $role, $channel, $trace)) !== null) {
            return $denied;
        }
        // Agents post through tools, gated by tool.invoke; humans need write.
        if (($actor['kind'] ?? null) === 'human' && ($denied = $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)) !== null) {
            return $denied;
        }

        return $this->allow($trace, 'channel_postable', 'The actor can post in this channel.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function channelManageMembersRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $channel = $this->expectResource($resource, 'channel');
        if ($channel === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a channel.');
        }
        if (($channel['channel_type'] ?? null) === 'dm') {
            return $this->deny($trace, 'dm_membership_fixed', 'Direct message participants cannot be changed.');
        }
        if (($denied = $this->channelReadable($actorId, $actor, $role, $channel, $trace)) !== null) {
            return $denied;
        }
        if (($denied = $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)) !== null) {
            return $denied;
        }
        if (($context['target_in_workspace'] ?? true) !== true) {
            return $this->deny($trace, 'target_not_in_workspace', 'The user to add is not a member or agent of this workspace.');
        }
        $channelRole = $channel['members'][$actorId] ?? null;
        if ($channelRole !== 'admin' && ! Capabilities::has($role, Capabilities::CHANNEL_MODERATE)) {
            return $this->deny($trace, 'channel_admin_required', 'Only a channel admin or a workspace admin can change channel members.');
        }

        return $this->allow($trace, 'channel_admin', 'The actor administers this channel.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function channelJoinRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $channel = $this->expectResource($resource, 'channel');
        if ($channel === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a channel.');
        }
        // A channel one cannot read stays hidden (404), not "not joinable".
        if (($denied = $this->channelReadable($actorId, $actor, $role, $channel, $trace)) !== null) {
            return $denied;
        }
        if (! in_array($channel['channel_type'] ?? null, self::OPEN_CHANNEL_TYPES, true)) {
            return $this->deny($trace, 'channel_not_joinable', 'Only public channels can be joined without an invitation.');
        }

        return $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)
            ?? $this->allow($trace, 'channel_joinable', 'Public channels are open to workspace members.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function channelLeaveRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $channel = $this->expectResource($resource, 'channel');
        if ($channel === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a channel.');
        }
        if (($channel['channel_type'] ?? null) === 'dm') {
            return $this->deny($trace, 'dm_membership_fixed', 'Direct message participants cannot be changed.');
        }
        if (! array_key_exists($actorId, (array) ($channel['members'] ?? []))) {
            return $this->deny($trace, 'channel_not_member', 'The actor is not a member of this channel.');
        }

        return $this->allow($trace, 'channel_member', 'Members may leave a channel.');
    }

    /**
     * message.delete and message.reaction.remove: the owner of the message
     * (or reaction) may remove it; channel admins and workspace moderators
     * may remove others' in non-DM channels they can read.
     *
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function messageOwnedRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $type = $action === 'message.delete' ? 'message' : 'reaction';
        $item = $this->expectResource($resource, $type);
        $channel = is_array($item['channel'] ?? null) ? $item['channel'] : null;
        if ($item === null || $channel === null) {
            return $this->deny($trace, 'resource_type_mismatch', "This action needs a {$type} with its channel.");
        }
        if (($denied = $this->channelReadable($actorId, $actor, $role, $channel, $trace)) !== null) {
            return $denied;
        }
        if (($denied = $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)) !== null) {
            return $denied;
        }
        if (($item['owner_id'] ?? null) === $actorId) {
            return $this->allow($trace, "{$type}_owner", "The actor owns this {$type}.");
        }
        $moderator = ($channel['members'][$actorId] ?? null) === 'admin' || Capabilities::has($role, Capabilities::CHANNEL_MODERATE);
        if (($channel['channel_type'] ?? null) !== 'dm' && $moderator) {
            return $this->allow($trace, 'channel_moderator', "A channel moderator may remove another member's {$type}.");
        }

        return $this->deny($trace, "{$type}_owner_required", "Only the author (or a channel moderator outside DMs) may remove this {$type}.");
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function documentReadRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $doc = $this->expectResource($resource, 'document');
        if ($doc === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a document.');
        }

        return $this->requireCapability($role, Capabilities::WORKSPACE_READ, $trace)
            ?? $this->documentShareDenial($actorId, $doc, null, $trace)
            ?? $this->allow($trace, 'document_readable', 'The actor can read this document.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function documentWriteRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $doc = $this->expectResource($resource, 'document');
        if ($doc === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a document.');
        }

        return $this->documentWritable($actorId, $role, $doc, $trace)
            ?? $this->allow($trace, 'document_writable', 'The actor can edit this document.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function documentCreateRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        if (($denied = $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)) !== null) {
            return $denied;
        }
        // Root-level create: the resource is the workspace itself.
        if ($resource === null || ($resource['type'] ?? null) === 'workspace') {
            return $this->allow($trace, 'document_create_root', 'Members may create documents at the root.');
        }
        $parent = $this->expectResource($resource, 'document');
        if ($parent === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'A document parent must be a document folder.');
        }
        if (($parent['is_folder'] ?? false) !== true) {
            return $this->deny($trace, 'document_parent_not_folder', 'Documents can only be created inside a folder.');
        }

        return $this->documentWritable($actorId, $role, $parent, $trace)
            ?? $this->allow($trace, 'document_parent_writable', 'The actor can write in this folder.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function documentCommentRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $doc = $this->expectResource($resource, 'document');
        if ($doc === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a document.');
        }

        return $this->documentShareDenial($actorId, $doc, null, $trace)
            ?? $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)
            ?? $this->allow($trace, 'document_commentable', 'Readers with write access may comment.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function documentCommentManageRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $comment = $this->expectResource($resource, 'document_comment');
        $doc = is_array($comment['document'] ?? null) ? $comment['document'] : null;
        if ($comment === null || $doc === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a document comment with its document.');
        }
        if (($denied = $this->documentShareDenial($actorId, $doc, null, $trace)) !== null) {
            return $denied;
        }
        if (($denied = $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)) !== null) {
            return $denied;
        }
        if (($comment['owner_id'] ?? null) === $actorId) {
            return $this->allow($trace, 'comment_owner', 'The actor wrote this comment.');
        }
        $editorTrace = [];
        if ($this->documentWritable($actorId, $role, $doc, $editorTrace) === null) {
            return $this->allow($trace, 'document_editor', 'Document editors may manage comments.');
        }

        return $this->deny($trace, 'comment_owner_required', 'Only the comment author or a document editor may change this comment.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function agentCreateRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        if (($denied = $this->requireCapability($role, Capabilities::AGENT_CREATE, $trace)) !== null) {
            return $denied;
        }
        $managerId = $context['manager_id'] ?? null;
        if (is_string($managerId) && $managerId !== $actorId && ! Capabilities::has($role, Capabilities::AGENT_MANAGE)) {
            return $this->deny($trace, 'agent_manager_assignment_requires_admin', 'Only an admin can create an agent managed by someone else.');
        }

        return $this->allow($trace, 'agent_create', 'The actor may create agents.');
    }

    /**
     * agent.update, agent.update_identity, agent.delete: admins manage every
     * agent; a member manages the agents they directly manage.
     *
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function agentManagedRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $agent = $this->expectResource($resource, 'agent');
        if ($agent === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs an agent.');
        }
        if (($denied = $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)) !== null) {
            return $denied;
        }
        if (Capabilities::has($role, Capabilities::AGENT_MANAGE)) {
            return $this->allow($trace, 'agent_manage_capability', "Role {$role} manages every agent.");
        }
        if (($agent['manager_id'] ?? null) === $actorId) {
            return $this->allow($trace, 'agent_manager', 'The actor manages this agent.');
        }

        return $this->deny($trace, 'agent_manager_required', "Only this agent's manager or a workspace admin can change it.");
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function agentAdminRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        if ($this->expectResource($resource, 'agent') === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs an agent.');
        }
        $capability = $action === 'agent.update_grants' ? Capabilities::AGENT_GRANTS : Capabilities::AGENT_MANAGE;

        return $this->requireCapability($role, $capability, $trace)
            ?? $this->allow($trace, 'capability', "Role {$role} holds {$capability}.");
    }

    /**
     * Agent-to-agent delegation (contact_agent, subagents). Ported from
     * AgentPermissionResolver::canContactAgent, which now delegates here.
     *
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function agentDelegateRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $target = $this->expectResource($resource, 'agent');
        if ($target === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'Delegation needs a target agent.');
        }
        $targetId = (string) ($target['id'] ?? '');
        if ($targetId === $actorId) {
            return $this->deny($trace, 'agent_delegate_self', 'An agent cannot delegate to itself.');
        }
        if ($targetId === ($actor['manager_id'] ?? null) || ($target['manager_id'] ?? null) === $actorId) {
            return $this->allow($trace, 'agent_manager_chain', 'Agents may always reach their manager and direct reports.');
        }

        $rules = is_array($actor['contact_rules'] ?? null) ? $actor['contact_rules'] : [];
        foreach ([$targetId, '*'] as $key) {
            $rule = $rules[$key] ?? null;
            if (! is_array($rule)) {
                continue;
            }
            if (($rule['permission'] ?? null) === 'deny') {
                return $this->deny($trace, 'agent_contact_denied', 'An admin rule denies contacting this agent.');
            }

            return ($rule['requires_approval'] ?? false) === true
                ? $this->approval($trace, 'agent_contact_rule_requires_approval', 'An admin rule requires approval to contact this agent.')
                : $this->allow($trace, 'agent_contact_rule', 'An admin rule allows contacting this agent.');
        }

        // Contacting a peer outside the manager chain creates work for another
        // agent, so it needs approval unless an admin rule says otherwise.
        return $this->approval($trace, 'agent_contact_peer_requires_approval', 'Contacting a peer outside the manager chain needs approval.');
    }

    /**
     * Agent tool gate (reversibility, not "write"). Ported from
     * AgentPermissionResolver::resolveToolPermission, which builds the facts
     * and delegates here. Invariant order — do not reorder:
     * explicit deny → floor → control-flow exempt → on-behalf-of → trigger
     * (a trigger is never consent) → scoped grants → per-agent admin rule →
     * preset default gate.
     *
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function toolInvokeRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $class = (string) ($context['action_class'] ?? C::EXTERNAL_SEND);
        $explicit = is_array($context['explicit_permission'] ?? null) ? $context['explicit_permission'] : null;
        $trace[] = "action_class:{$class}";

        if (($explicit['permission'] ?? null) === 'deny') {
            return $this->deny($trace, 'tool_explicitly_denied', 'An admin rule denies this tool for this agent.');
        }
        if ($class === C::FLOOR) {
            return $this->deny($trace, 'floor', 'This action is on the hard-deny floor; only a human can do it.');
        }
        if (($context['approval_exempt'] ?? false) === true) {
            return $this->allow($trace, 'tool_control_flow_exempt', 'Control-flow tools never need approval.');
        }

        $principalId = $request['on_behalf_of'] ?? null;
        if (is_string($principalId) && $principalId !== '') {
            $principal = $facts['actors'][$principalId] ?? null;
            $name = is_array($principal) ? (string) ($principal['name'] ?? $principalId) : 'the requester';
            if (! is_array($principal) || ($principal['kind'] ?? null) !== 'human' || ! $this->belongs($principalId, $principal, $workspace)) {
                return $this->deny($trace, 'on_behalf_of_not_member', "Acting on behalf of {$name}, who is no longer a member of this workspace.");
            }
            $principalRole = $this->roleOf($principalId, $principal, $workspace);
            if (in_array($class, self::ADMIN_PRINCIPAL_CLASSES, true) && ! in_array($principalRole, [Capabilities::ROLE_OWNER, Capabilities::ROLE_ADMIN], true)) {
                return $this->deny($trace, 'on_behalf_of_requires_admin', "Acting on behalf of {$name}, who is not a workspace admin and cannot authorize this {$class} action.");
            }
            $trace[] = 'on_behalf_of:ok';
        }

        $external = in_array($class, C::EXTERNAL_CLASSES, true);
        if ($external && ($context['trigger_started'] ?? false) === true) {
            return $this->approval($trace, 'trigger_is_not_consent', 'A trigger is never consent: external actions in trigger-started runs always ask.');
        }

        $alwaysAllowed = ! in_array($class, PresetGates::NO_ALWAYS_EXCEPTION, true);
        $grant = is_array($context['grant'] ?? null) ? $context['grant'] : [];
        if (($grant['scoped'] ?? false) === true || ($alwaysAllowed && ($grant['always'] ?? false) === true)) {
            return $this->allow($trace, 'policy_grant', 'A scoped exception covers this call.');
        }

        if ($explicit !== null) {
            if (($explicit['requires_approval'] ?? false) === true) {
                return $this->approval($trace, 'agent_rule_requires_approval', 'An admin rule requires approval for this tool.');
            }
            if ($alwaysAllowed) {
                return $this->allow($trace, 'agent_rule_allows', 'An admin rule allows this tool without approval.');
            }
        }

        $preset = (string) ($workspace['preset'] ?? 'team');
        $gate = (new PresetGates)->gate($class, $preset);
        $trace[] = "preset:{$preset}:{$gate}";

        return match ($gate) {
            PresetGates::DENY => $this->deny($trace, 'preset_deny', "The {$preset} preset denies {$class} actions."),
            PresetGates::APPROVAL => $this->approval($trace, 'preset_requires_approval', "The {$preset} preset asks before {$class} actions."),
            default => $this->allow($trace, 'preset_allows', "The {$preset} preset allows {$class} actions."),
        };
    }

    /**
     * Approval resolution. Order and codes match ApprovalResolutionPolicy,
     * which delegates here: agents never approve; the approval must have a
     * workspace; no self-approval; the responder needs approval.resolve.
     *
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  list<string>  $trace
     */
    private function approvalResolveRule(string $actorId, array $actor, array $workspace, ?array $resource, array &$trace): Decision
    {
        // Invariant: an agent never approves anything, in any workspace.
        if (($actor['kind'] ?? null) !== 'human') {
            return $this->deny($trace, 'approval_agent_cannot_resolve', 'Agents cannot approve or reject approval requests.');
        }
        $approval = $this->expectResource($resource, 'approval');
        if ($approval === null || ($approval['workspace_id'] ?? null) === null) {
            return $this->deny($trace, 'approval_workspace_unknown', 'This approval is not linked to a workspace and cannot be resolved.');
        }
        if ($approval['workspace_id'] !== $workspace['id']) {
            return $this->deny($trace, 'workspace_boundary', 'The approval belongs to a different workspace.');
        }
        if (($approval['requester_id'] ?? null) === $actorId) {
            return $this->deny($trace, 'approval_self_resolution_forbidden', 'You cannot resolve an approval you requested yourself.');
        }
        $role = $this->belongs($actorId, $actor, $workspace) ? $this->roleOf($actorId, $actor, $workspace) : null;
        if (! Capabilities::has($role, Capabilities::APPROVAL_RESOLVE)) {
            return $this->deny($trace, 'approval_role_required', 'Only workspace admins and approvers can resolve approval requests.');
        }

        return $this->allow($trace, 'approval_resolver', 'The responder may resolve this approval.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function memberChangeRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        $member = $this->expectResource($resource, 'member');
        if ($member === null) {
            return $this->deny($trace, 'resource_type_mismatch', 'This action needs a workspace member.');
        }
        if (($denied = $this->requireCapability($role, Capabilities::MEMBER_MANAGE, $trace)) !== null) {
            return $denied;
        }
        // Owner protection: the owner keeps the admin role and membership.
        $targetUserId = $member['user_id'] ?? null;
        $isOwner = is_string($targetUserId) && $targetUserId === ($workspace['owner_id'] ?? null);
        if ($isOwner && ($action === 'member.remove' || ($context['role'] ?? null) !== Capabilities::ROLE_ADMIN)) {
            return $this->deny($trace, 'owner_protected', 'The workspace owner cannot be removed or demoted.');
        }

        return $this->allow($trace, 'capability', 'The actor manages workspace members.');
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     * @param  array<string, mixed>|null  $resource
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $facts
     * @param  list<string>  $trace
     */
    private function gatewayInvokeRule(string $action, string $actorId, array $actor, ?string $role, array $workspace, ?array $resource, array $context, array $request, array $facts, array &$trace): Decision
    {
        if (($actor['revoked'] ?? false) === true) {
            return $this->deny($trace, 'api_key_revoked', 'This API key has been revoked.');
        }
        $expiresAt = $actor['expires_at'] ?? null;
        if (! is_string($expiresAt) || $expiresAt === '') {
            return $this->deny($trace, 'api_key_expiry_required', 'This API key has no expiry; create a new key with an expiry.');
        }
        $now = strtotime((string) ($facts['now'] ?? ''));
        $expires = strtotime($expiresAt);
        if ($now === false || $expires === false || $expires <= $now) {
            return $this->deny($trace, 'api_key_expired', 'This API key has expired.');
        }

        $operation = (string) ($context['operation'] ?? '');
        $scopes = is_array($actor['scopes'] ?? null) ? $actor['scopes'] : [];
        if (! in_array($operation, self::GATEWAY_OPERATIONS, true) || ! in_array($operation, (array) ($scopes['actions'] ?? []), true)) {
            return $this->deny($trace, 'api_key_scope_action', "This API key is not scoped for {$operation}.");
        }

        // Listing models needs no model scope; the list is filtered instead.
        if (($resource['type'] ?? null) === 'ai_model') {
            $models = (array) ($scopes['models'] ?? []);
            if (! in_array('*', $models, true) && ! in_array($resource['id'] ?? null, $models, true)) {
                return $this->deny($trace, 'api_key_scope_model', 'This API key is not scoped for this model.');
            }
        }

        return $this->allow($trace, 'api_key_scoped', 'The key is live and scoped for this call.');
    }

    // ─── Shared predicates ────────────────────────────────────────────────

    /**
     * Channel visibility. Humans: public channels are open to every member
     * (viewers included); private channels and DMs need membership; bridged
     * external channels need membership or workspace admin (the bridge is an
     * admin-configured integration). Agents: DMs and private channels need
     * membership or an explicit grant of that channel; their channel grant
     * list (null = unrestricted) must include the channel.
     * AuthorizationScopes::readableChannels() is the SQL form of this rule;
     * keep the two in step (AuthorizationScopesTest checks they agree).
     *
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $channel
     * @param  list<string>  $trace
     */
    private function channelReadable(string $actorId, array $actor, ?string $role, array $channel, array &$trace): ?Decision
    {
        $type = (string) ($channel['channel_type'] ?? 'private');
        $isMember = array_key_exists($actorId, (array) ($channel['members'] ?? []));

        if (($actor['kind'] ?? null) === 'agent') {
            $grants = $actor['channel_grants'] ?? null;
            $granted = is_array($grants) && in_array($channel['id'] ?? null, $grants, true);
            // Other principals' DMs stay hidden unless an admin granted the
            // channel by explicit id (same rule as the VFS channel mount).
            if ($type === 'dm' && ! $isMember && ! $granted) {
                return $this->deny($trace, 'channel_not_member', 'Agents read only the direct messages they take part in.');
            }
            if (is_array($grants) && ! $granted) {
                return $this->deny($trace, 'agent_channel_not_granted', "This agent's channel grants do not include this channel.");
            }
            if ($type === 'private' && ! $isMember && ! $granted) {
                return $this->deny($trace, 'channel_not_member', 'Private channels are readable by their members only.');
            }
            $trace[] = 'channel:readable';

            return null;
        }

        if (($denied = $this->requireCapability($role, Capabilities::WORKSPACE_READ, $trace)) !== null) {
            return $denied;
        }
        if (in_array($type, self::OPEN_CHANNEL_TYPES, true) || $isMember) {
            $trace[] = 'channel:readable';

            return null;
        }
        if ($type === 'external' && in_array($role, [Capabilities::ROLE_OWNER, Capabilities::ROLE_ADMIN], true)) {
            $trace[] = 'channel:external_admin';

            return null;
        }

        return $this->deny($trace, 'channel_not_member', 'This conversation is readable by its members only.');
    }

    /**
     * Document sharing rows (until Spaces replace them): every document in the
     * chain (itself, then ancestors) that has rows is restricted to its author
     * and the listed users; for $role `editor` the listed user must be an
     * editor. No admin override: sharing rows are private by design.
     *
     * @param  array<string, mixed>  $doc
     * @param  list<string>  $trace
     */
    private function documentShareDenial(string $actorId, array $doc, ?string $requiredRole, array &$trace): ?Decision
    {
        foreach ((array) ($doc['chain'] ?? []) as $node) {
            $shares = (array) ($node['shares'] ?? []);
            if ($shares === [] || ($node['author_id'] ?? null) === $actorId) {
                continue;
            }
            $mine = $shares[$actorId] ?? null;
            if ($mine === null) {
                return $this->deny($trace, 'document_not_shared', 'This document is shared with specific people and the actor is not one of them.');
            }
            if ($requiredRole === 'editor' && $mine !== 'editor') {
                return $this->deny($trace, 'document_editor_required', 'This document is shared with the actor as a viewer only.');
            }
        }
        $trace[] = 'document:shares_ok';

        return null;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  list<string>  $trace
     */
    private function documentWritable(string $actorId, ?string $role, array $doc, array &$trace): ?Decision
    {
        if (($denied = $this->documentShareDenial($actorId, $doc, null, $trace)) !== null) {
            return $denied;
        }
        if (($denied = $this->requireCapability($role, Capabilities::WORKSPACE_WRITE, $trace)) !== null) {
            return $denied;
        }
        if (($doc['is_system'] ?? false) === true) {
            return $this->deny($trace, 'system_document', 'Agent identity and memory documents change only through the agent identity endpoints.');
        }

        return $this->documentShareDenial($actorId, $doc, 'editor', $trace);
    }

    /**
     * @param  list<string>  $trace
     */
    private function requireCapability(?string $role, string $capability, array &$trace): ?Decision
    {
        if (Capabilities::has($role, $capability)) {
            $trace[] = "capability:{$capability}";

            return null;
        }
        // Viewers hold only workspace.read; every other missing capability
        // is reported as the read-only role, which is the actionable reason.
        if ($role === Capabilities::ROLE_VIEWER) {
            return $this->deny($trace, 'role_read_only', 'Viewers have read-only access to this workspace.');
        }

        return $this->deny($trace, 'capability_required', "This needs the {$capability} capability, which the ".($role ?? 'actor')." role does not hold.");
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     */
    private function belongs(string $actorId, array $actor, array $workspace): bool
    {
        return match ($actor['kind'] ?? null) {
            'human' => ($actor['workspace_role'] ?? null) !== null || ($workspace['owner_id'] ?? null) === $actorId,
            'agent', 'api_key' => ($actor['workspace_id'] ?? null) === ($workspace['id'] ?? null),
            default => false,
        };
    }

    /**
     * Effective human role: owner when the workspace names them, otherwise
     * the stored role. Unknown stored roles hold nothing (fail closed).
     *
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $workspace
     */
    private function roleOf(string $actorId, array $actor, array $workspace): ?string
    {
        if (($actor['kind'] ?? null) !== 'human') {
            return null;
        }
        if (($workspace['owner_id'] ?? null) === $actorId) {
            return Capabilities::ROLE_OWNER;
        }
        $role = $actor['workspace_role'] ?? null;

        return is_string($role) && in_array($role, Capabilities::STORED_ROLES, true) ? $role : null;
    }

    /**
     * @param  array<string, mixed>|null  $resource
     * @return array<string, mixed>|null
     */
    private function expectResource(?array $resource, string $type): ?array
    {
        return $resource !== null && ($resource['type'] ?? null) === $type ? $resource : null;
    }

    /** @return array{rule: string, actors: list<string>, audit: string}|null */
    private function spec(string $action): ?array
    {
        if (str_starts_with($action, 'settings.command.')) {
            return isset(self::SETTINGS_COMMANDS[substr($action, strlen('settings.command.'))])
                ? ['rule' => 'capabilityRule', 'actors' => ['human'], 'audit' => self::AUDIT_ALWAYS]
                : null;
        }

        return self::ACTIONS[$action] ?? null;
    }

    /**
     * @param  list<string>  $trace
     */
    private function allow(array &$trace, string $code, string $reason): Decision
    {
        return $this->finish($trace, Decision::ALLOW, $code, $reason);
    }

    /**
     * @param  list<string>  $trace
     */
    private function deny(array &$trace, string $code, string $reason): Decision
    {
        return $this->finish($trace, Decision::DENY, $code, $reason);
    }

    /**
     * @param  list<string>  $trace
     */
    private function approval(array &$trace, string $code, string $reason): Decision
    {
        return $this->finish($trace, Decision::APPROVAL_REQUIRED, $code, $reason);
    }

    /**
     * @param  list<string>  $trace
     */
    private function finish(array &$trace, string $effect, string $code, string $reason): Decision
    {
        $trace[] = "{$effect}:{$code}";

        return new Decision($effect, $code, $reason, $trace, self::VERSION);
    }
}
