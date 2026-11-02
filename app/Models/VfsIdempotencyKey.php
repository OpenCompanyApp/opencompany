<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Stored result of a VFS mutation that carried an `idempotency_key`.
 *
 * Owned by OpenCompanyVfs (RemembersVfsIdempotency). Scope is the acting
 * agent; rows past `expires_at` (24 h) are treated as absent.
 *
 * @property array<string, mixed>|null $result
 * @property string $operation
 * @property string $path
 * @property \Illuminate\Support\Carbon $expires_at
 */
class VfsIdempotencyKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'agent_id',
        'idempotency_key',
        'operation',
        'path',
        'result',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
