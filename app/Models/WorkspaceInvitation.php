<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending invitation for a human to join a workspace.
 *
 * Invitations carry the role to assign on acceptance and keep the token separate
 * from WorkspaceMember so unaccepted users do not affect access checks.
 */
class WorkspaceInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'token',
        'inviter_id',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }
}
