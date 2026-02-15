<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'owner_id',
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

    public function scheduledAutomations(): HasMany
    {
        return $this->hasMany(ScheduledAutomation::class);
    }

    public function mcpServers(): HasMany
    {
        return $this->hasMany(McpServer::class);
    }
}
