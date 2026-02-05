<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListTemplate extends Model
{
    protected $table = 'list_templates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'default_title',
        'default_description',
        'default_priority',
        'default_assignee_id',
        'estimated_cost',
        'tags',
        'created_by_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'estimated_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(ListAutomationRule::class, 'list_template_id');
    }
}
