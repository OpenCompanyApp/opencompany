<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-granted, scoped exception to the default approval policy.
 *
 * Owns: one exact tool slug for one agent, in one of five scopes —
 * `once` (consumed on first execution), `run`, `task`, `until` (expires_at)
 * and `always`. Matching is exact; there are no wildcards or patterns.
 *
 * Does not own: denies (AgentPermission deny rows win over any grant), the
 * hard-deny floor (planned, never unlockable), or approval of an individual
 * call (that is an ApprovalRequest).
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $agent_id
 * @property string $tool_slug
 * @property string $scope
 * @property string|null $run_id
 * @property string|null $task_id
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $consumed_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property string|null $granted_by_id
 * @property string|null $reason
 */
class PolicyGrant extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const SCOPES = ['once', 'run', 'task', 'until', 'always'];

    protected $fillable = [
        'workspace_id', 'agent_id', 'tool_slug', 'scope', 'run_id', 'task_id',
        'expires_at', 'consumed_at', 'revoked_at', 'granted_by_id', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
