<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListStatus extends Model
{
    protected $table = 'list_statuses';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'color',
        'icon',
        'is_done',
        'is_default',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['isDone'] = $this->is_done;
        $array['isDefault'] = $this->is_default;

        return $array;
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'status', 'slug');
    }
}
