<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property string $type
 * @property bool $required
 */
class DataTableColumn extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'table_id',
        'name',
        'type',
        'options',
        'order',
        'required',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DataTable::class, 'table_id');
    }
}
