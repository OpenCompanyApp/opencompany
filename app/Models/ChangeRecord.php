<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * One recorded agent change to governed content (governance-model.md,
 * "Change sets": reversible edits auto-apply, recorded so a run can be
 * reverted). Written by RecordsAgentChanges while an ActiveAgentRun is bound;
 * consumed by RunReverter. Append-only apart from the revert stamp.
 *
 * `before`/`after` hold raw attribute values for the changed columns only.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string|null $run_id
 * @property string|null $agent_id
 * @property string $subject_type model class
 * @property string $subject_id
 * @property string $operation created|updated|deleted|restored
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property \Illuminate\Support\Carbon|null $reverted_at
 * @property string|null $reverted_by_id
 */
class ChangeRecord extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'workspace_id', 'run_id', 'agent_id', 'subject_type', 'subject_id',
        'operation', 'before', 'after', 'reverted_at', 'reverted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'reverted_at' => 'datetime',
        ];
    }
}
