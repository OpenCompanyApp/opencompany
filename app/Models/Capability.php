<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Capability extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'display_name',
        'description',
        'icon',
        'category',
        'kind',
        'default_enabled',
        'default_requires_approval',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_enabled' => 'boolean',
            'default_requires_approval' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['displayName'] = $this->display_name;
        $array['defaultEnabled'] = $this->default_enabled;
        $array['defaultRequiresApproval'] = $this->default_requires_approval;
        $array['sortOrder'] = $this->sort_order;

        return $array;
    }

    // Scopes

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopeDefaultEnabled(Builder $query): Builder
    {
        return $query->where('default_enabled', true);
    }
}
