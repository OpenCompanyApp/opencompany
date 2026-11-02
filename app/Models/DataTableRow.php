<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row of a workspace data table; `data` is keyed by column id.
 *
 * Deletes are soft (governance model: delete means trash); the VFS
 * `rows/.trash/` view restores them. History lives in DataTableRowVersion.
 *
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class DataTableRow extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'table_id',
        'data',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /** @return BelongsTo<DataTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'table_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
