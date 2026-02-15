<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

/**
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property string $icon
 * @property bool $is_done
 * @property bool $is_default
 * @property int $position
 */
class ListStatus extends Model
{
    use BelongsToWorkspace;

    protected $table = 'list_statuses';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
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

    /** @return HasMany<ListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'status', 'slug');
    }
}
