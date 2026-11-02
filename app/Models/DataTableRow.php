<?php

namespace App\Models;

use App\Models\Concerns\RecordsAgentChanges;
use App\Models\Contracts\RevertibleContent;
use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a workspace data table; `data` is keyed by column id.
 *
 * Deletes are soft (governance model: delete means trash); the VFS
 * `rows/.trash/` view restores them. History lives in DataTableRowVersion.
 *
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class DataTableRow extends Model implements RevertibleContent
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    // Trashable: delete moves a row to trash; forceDelete is the purge.
    use HasFactory, HasUuids, RecordsAgentChanges, Trashable;

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
