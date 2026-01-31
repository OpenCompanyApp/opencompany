<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTableRow extends Model
{
    use HasFactory, HasUuids;

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

    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'table_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
