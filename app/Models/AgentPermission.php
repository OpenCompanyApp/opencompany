<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted permission rule for one agent and one scoped resource.
 *
 * scope_type names the resource family (`tool`, `channel`, `folder`,
 * `file_folder`, `integration`, `agent`); scope_key stores the concrete slug or
 * ID. AgentPermissionService combines these rows with behavior_mode defaults.
 */
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // Scopes

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAgent(Builder $query, string $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTools(Builder $query): Builder
    {
        return $query->where('scope_type', 'tool');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeChannels(Builder $query): Builder
    {
        return $query->where('scope_type', 'channel');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFolders(Builder $query): Builder
    {
        return $query->where('scope_type', 'folder');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeIntegrations(Builder $query): Builder
    {
        return $query->where('scope_type', 'integration');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAllowed(Builder $query): Builder
    {
        return $query->where('permission', 'allow');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDenied(Builder $query): Builder
    {
        return $query->where('permission', 'deny');
    }
}
