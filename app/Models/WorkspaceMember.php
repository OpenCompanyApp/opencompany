<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Join model for human workspace membership and role.
 *
 * Agents use a direct workspace_id on User; this table represents humans who
 * can switch between workspaces and administer tenant settings.
 *
 * Roles (stored as a free string in workspace_members.role):
 *  - admin:    full workspace administration; may resolve approvals.
 *  - approver: a regular member who may additionally approve/reject approval
 *              requests (see ApprovalResolutionPolicy). No admin settings.
 *  - member:   regular collaborator; can see approvals but not resolve them.
 * ROLES is the single allow-list used by request validation; extend it here.
 */
class WorkspaceMember extends Model
{
    use HasUuids;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_APPROVER = 'approver';

    public const ROLE_MEMBER = 'member';

    /** @var list<string> */
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_APPROVER, self::ROLE_MEMBER];

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
    ];

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canResolveApprovals(): bool
    {
        return self::roleCanResolveApprovals($this->role);
    }

    /**
     * Role half of the approval rule. Identity rules (agents, self-approval)
     * live in ApprovalResolutionPolicy; call that, not this, to authorize.
     */
    public static function roleCanResolveApprovals(?string $role): bool
    {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_APPROVER], true);
    }
}
