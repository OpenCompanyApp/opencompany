<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListAutomationRule extends Model
{
    protected $table = 'list_automation_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'trigger_type',
        'trigger_conditions',
        'action_type',
        'action_config',
        'list_template_id',
        'is_active',
        'last_triggered_at',
        'trigger_count',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'trigger_conditions' => 'array',
            'action_config' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ListTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ListTemplate::class, 'list_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
