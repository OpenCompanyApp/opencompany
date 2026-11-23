<?php

namespace App\Domain\Authorization\Engine;

/**
 * Fixed human roles and the named capabilities each one holds (ROADMAP Phase 0
 * P0 item 1; docs/architecture/governance-model.md "Roles and principals").
 *
 * Owns: the one reviewable role → capability table. Roles are fixed in code;
 * custom roles come later and may only ever be narrower than these. The table
 * is part of the policy version (PolicyEngine::policyHash()), so changing it
 * changes the hash recorded in every authorization ledger entry.
 *
 * Does not own: relationships (an agent's manager, a channel admin, a
 * document's author or sharing rows). Those are facts about a resource that
 * PolicyEngine combines with these capabilities.
 *
 * Roles:
 * - owner:    derived from workspaces.owner_id (stored role is `admin`); holds
 *             every capability, and is the only holder of `settings.danger`.
 * - admin:    workspace administration.
 * - approver: a member who may also resolve approvals.
 * - member:   regular collaborator.
 * - viewer:   read-only member; may read what members may read, never write.
 *
 * Agents never hold any of these capabilities. Agents act through tools
 * (`tool.invoke`) under the reversibility gates, their own grants and the
 * on-behalf-of intersection; an agent never approves anything.
 *
 * Pure PHP on purpose: no Laravel, no database. The conformance vectors in
 * tests/conformance/authorization run against PolicyEngine with plain facts,
 * and a later Rust governance crate must pass the same vectors (GIN-2057).
 */
final class Capabilities
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_APPROVER = 'approver';
    public const ROLE_MEMBER = 'member';
    public const ROLE_VIEWER = 'viewer';

    /** Roles that can be stored on workspace_members.role (owner is derived). */
    public const STORED_ROLES = [self::ROLE_ADMIN, self::ROLE_APPROVER, self::ROLE_MEMBER, self::ROLE_VIEWER];

    public const WORKSPACE_READ = 'workspace.read';
    public const WORKSPACE_WRITE = 'workspace.write';
    public const CHANNEL_CREATE = 'channel.create';
    /** Moderate non-DM channels the actor can read: membership and others' messages. */
    public const CHANNEL_MODERATE = 'channel.moderate';
    public const AGENT_CREATE = 'agent.create';
    /** Manage any agent in the workspace, not only the agents one manages. */
    public const AGENT_MANAGE = 'agent.manage';
    /** Change what an agent may do: tool, channel, folder and integration grants. */
    public const AGENT_GRANTS = 'agent.grants';
    public const APPROVAL_RESOLVE = 'approval.resolve';
    public const MEMBER_MANAGE = 'member.manage';
    public const SETTINGS_READ = 'settings.read';
    public const SETTINGS_UPDATE = 'settings.update';
    /** Operational, non-destructive settings commands (pause, retry, refresh). */
    public const SETTINGS_OPERATE = 'settings.operate';
    /** Destructive settings commands that erase data (memory, embeddings). */
    public const SETTINGS_DANGER = 'settings.danger';
    public const AI_GATEWAY_MANAGE = 'ai_gateway.manage';
    public const AUDIT_READ = 'audit.read';
    public const POLICY_EDIT = 'policy.edit';

    private const MEMBER_BASE = [
        self::WORKSPACE_READ, self::WORKSPACE_WRITE, self::CHANNEL_CREATE, self::AGENT_CREATE,
    ];

    private const ADMIN = [
        self::WORKSPACE_READ, self::WORKSPACE_WRITE, self::CHANNEL_CREATE, self::CHANNEL_MODERATE,
        self::AGENT_CREATE, self::AGENT_MANAGE, self::AGENT_GRANTS, self::APPROVAL_RESOLVE,
        self::MEMBER_MANAGE, self::SETTINGS_READ, self::SETTINGS_UPDATE, self::SETTINGS_OPERATE,
        self::AI_GATEWAY_MANAGE, self::AUDIT_READ, self::POLICY_EDIT,
    ];

    /** @var array<string, list<string>> */
    public const TABLE = [
        self::ROLE_OWNER => [...self::ADMIN, self::SETTINGS_DANGER],
        self::ROLE_ADMIN => self::ADMIN,
        self::ROLE_APPROVER => [...self::MEMBER_BASE, self::APPROVAL_RESOLVE],
        self::ROLE_MEMBER => self::MEMBER_BASE,
        self::ROLE_VIEWER => [self::WORKSPACE_READ],
    ];

    /** Whether a role holds a capability. Unknown or null roles hold nothing. */
    public static function has(?string $role, string $capability): bool
    {
        return $role !== null && in_array($capability, self::TABLE[$role] ?? [], true);
    }
}
