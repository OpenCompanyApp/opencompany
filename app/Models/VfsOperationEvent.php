<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Durable row of one VFS event (see App\Domain\Vfs\Events\VfsEvent for the
 * envelope and the list of types).
 *
 * Written only by DatabaseVfsEventSink. Rows store operation metadata,
 * versions and redacted arguments; virtual file contents, document bodies,
 * patch text and command output stay out of this table so the audit trail is
 * not a secondary content store or secret sink. The workspace event log will
 * replace this table; until then it also backs `/workspace/changes.ndjson`.
 *
 * @property int $id
 * @property string $event_id
 * @property string $workspace_id
 * @property string $type
 * @property string|null $agent_id
 * @property string $operation
 * @property string|null $path
 * @property string|null $canonical_path
 * @property string|null $backend_type
 * @property string|null $backend_id
 * @property string|null $after_version
 * @property string|null $run_id
 * @property array<string, mixed>|null $payload
 * @property Carbon $occurred_at
 */
class VfsOperationEvent extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'event_id',
        'workspace_id',
        'type',
        'source',
        'actor_type',
        'agent_id',
        'surface',
        'run_id',
        'causality_id',
        'depth',
        'operation',
        'effect',
        'decision',
        'action_class',
        'path',
        'canonical_path',
        'backend_type',
        'backend_id',
        'approval_id',
        'before_version',
        'after_version',
        'bytes',
        'idempotency_key',
        'error_code',
        'duration_ms',
        'committed',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'bytes' => 'integer',
            'duration_ms' => 'integer',
            'committed' => 'boolean',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
