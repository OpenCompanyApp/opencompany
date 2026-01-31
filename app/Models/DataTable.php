<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTable extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(DataTableColumn::class, 'table_id')->orderBy('order');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DataTableRow::class, 'table_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(DataTableView::class, 'table_id');
    }
}
