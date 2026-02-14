<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property int $columnsCount
 * @property int $rowsCount
 */
class DataTable extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'created_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DataTableColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(DataTableColumn::class, 'table_id')->orderBy('order');
    }

    /** @return HasMany<DataTableRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(DataTableRow::class, 'table_id');
    }

    /** @return HasMany<DataTableView, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(DataTableView::class, 'table_id');
    }
}
