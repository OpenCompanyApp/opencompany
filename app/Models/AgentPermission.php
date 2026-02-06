<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AgentPermission extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'agent_id',
        'scope_type',
        'scope_key',
        'permission',
        'requires_approval',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // Scopes

    public function scopeForAgent(Builder $query, string $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeTools(Builder $query): Builder
    {
        return $query->where('scope_type', 'tool');
    }

    public function scopeChannels(Builder $query): Builder
    {
        return $query->where('scope_type', 'channel');
    }

    public function scopeFolders(Builder $query): Builder
    {
        return $query->where('scope_type', 'folder');
    }

    public function scopeIntegrations(Builder $query): Builder
    {
        return $query->where('scope_type', 'integration');
    }

    public function scopeAllowed(Builder $query): Builder
    {
        return $query->where('permission', 'allow');
    }

    public function scopeDenied(Builder $query): Builder
    {
        return $query->where('permission', 'deny');
    }
}
