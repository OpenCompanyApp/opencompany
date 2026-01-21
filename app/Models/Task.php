<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use HasFactory;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'status',
        'assignee_id',
        'priority',
        'cost',
        'estimated_cost',
        'channel_id',
        'position',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function collaboratorPivots(): HasMany
    {
        return $this->hasMany(TaskCollaborator::class);
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_collaborators');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }
}
