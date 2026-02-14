<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTableView extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'table_id',
        'name',
        'type',
        'filters',
        'sorts',
        'hidden_columns',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sorts' => 'array',
            'hidden_columns' => 'array',
        ];
    }

    /** @return BelongsTo<DataTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'table_id');
    }
}
