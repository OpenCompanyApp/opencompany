<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Durable audit row for VFS primitive tool activity.
 *
 * Rows store operation metadata and redacted arguments only. Virtual file
 * contents, document bodies, patch text, and command output stay out of this
 * table so the audit trail remains useful without becoming a secondary content
 * store or secret sink.
 */
class VfsOperationEvent extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'agent_id',
        'surface',
        'operation',
        'path',
        'cwd',
        'success',
        'error_code',
        'duration_ms',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
