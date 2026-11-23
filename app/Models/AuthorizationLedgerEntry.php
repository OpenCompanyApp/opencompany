<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of the append-only authorization ledger (GIN-1204).
 *
 * Written only by App\Domain\Authorization\Application\AuthorizationLedger,
 * which assigns the per-workspace sequence and hash chain. Invariant: the
 * model refuses updates and deletes; retention pruning, when it exists, is
 * an explicit maintenance command that records a chain checkpoint, never
 * application code. `details` is redacted before it is stored.
 *
 * @property string $id
 * @property string $workspace_id
 * @property int $sequence
 * @property string $kind
 * @property string $action
 * @property string $effect
 * @property string $reason_code
 * @property string|null $reason
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string|null $on_behalf_of_id
 * @property string|null $resource_type
 * @property string|null $resource_id
 * @property string $policy_version
 * @property string $policy_hash
 * @property string|null $correlation_id
 * @property string|null $run_id
 * @property array<string, mixed>|null $details
 * @property list<string>|null $trace
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property string|null $prev_hash
 * @property string $entry_hash
 */
class AuthorizationLedgerEntry extends Model
{
    use BelongsToWorkspace, HasUlids;

    public const KIND_DECISION = 'decision';

    public const KIND_SECURITY_CHANGE = 'security_change';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'sequence', 'kind', 'action', 'effect', 'reason_code', 'reason', 'actor_type',
        'actor_id', 'on_behalf_of_id', 'resource_type', 'resource_id', 'policy_version', 'policy_hash',
        'correlation_id', 'run_id', 'details', 'trace', 'occurred_at', 'prev_hash', 'entry_hash',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'details' => 'array',
            'trace' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Authorization ledger entries are append-only.'));
        static::deleting(fn () => throw new \LogicException('Authorization ledger entries are append-only.'));
    }
}
