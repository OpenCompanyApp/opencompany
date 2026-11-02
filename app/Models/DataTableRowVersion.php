<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a table row's data taken before a VFS write replaced it.
 *
 * Same contract as DocumentVersion and WorkspaceFileVersion: `data` (keyed by
 * column id, as stored on the row) is the state BEFORE change n, attributed to
 * the actor and run that made change n, with before/after version tokens.
 * Written only by the VFS row write paths (patch, overwrite, trash, restore).
 *
 * @property string $row_id
 * @property int $version_number
 * @property array<string, mixed> $data
 */
class DataTableRowVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'workspace_id',
        'table_id',
        'row_id',
        'version_number',
        'data',
        'author_id',
        'run_id',
        'before_version',
        'after_version',
        'change_description',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'version_number' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
