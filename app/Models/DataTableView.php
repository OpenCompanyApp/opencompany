<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTableView extends Model
{
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

    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'table_id');
    }
}
