<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant boundary for OpenCompany data.
 *
 * Human users join through workspace_members, while agents belong directly via
 * users.workspace_id. Most app queries should scope through this model or the
 * BelongsToWorkspace trait to avoid cross-workspace data leaks.
 */
class Workspace extends Model
{
    use HasUuids;

    /** Governance presets (docs/architecture/governance-model.md). */
    public const GOVERNANCE_PRESETS = ['personal', 'team', 'company'];

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'owner_id',
        'governance_preset',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'admin');
    }

    /**
     * Effective governance preset (pillar 5): the explicit `governance_preset`
     * when set, else `personal` for a single-member workspace and `team`
     * otherwise. Presets only choose default gates per action class; they
     * never relax the invariants enforced in AgentPermissionResolver.
     */
    public function effectiveGovernancePreset(): string
    {
        $preset = $this->governance_preset;
        if (is_string($preset) && in_array($preset, self::GOVERNANCE_PRESETS, true)) {
            return $preset;
        }

        return $this->members()->count() <= 1 ? 'personal' : 'team';
    }

    public function agents(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id')
            ->where('type', 'agent');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    // ─── Workspace-scoped entities ──────────────────────────────

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function integrationSettings(): HasMany
    {
        return $this->hasMany(IntegrationSetting::class);
    }

    public function appSettings(): HasMany
    {
        return $this->hasMany(AppSetting::class);
    }

    /** @return HasMany<Routine, $this> */
    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    public function mcpServers(): HasMany
    {
        return $this->hasMany(McpServer::class);
    }
}
