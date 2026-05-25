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
 */
class WorkspaceMember extends Model
{
    use HasUuids;

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
        return $this->role === 'admin';
    }
}
