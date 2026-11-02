<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the append-only workspace event log
 * (docs/planning/connections-events-routines.md, "Events").
 *
 * Invariant: events are never updated or deleted by application code; the
 * model refuses both. Retention/pruning is a separate, explicit maintenance
 * concern. `payload` is untrusted data whenever it reaches a prompt.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $type
 * @property string $source
 * @property string|null $source_ref
 * @property string|null $subject
 * @property string $actor_type
 * @property string|null $actor_id
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $payload
 * @property string $causality_id
 * @property string|null $parent_event_id
 * @property int $depth
 * @property string|null $run_id
 */
class WorkspaceEvent extends Model
{
    use BelongsToWorkspace, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'type', 'source', 'source_ref', 'subject', 'actor_type', 'actor_id',
        'occurred_at', 'recorded_at', 'idempotency_key', 'payload', 'causality_id',
        'parent_event_id', 'depth', 'run_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'depth' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Workspace events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Workspace events are append-only.'));
    }
}
