<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adds workspace scoping to Eloquent models.
 *
 * Uses explicit scoping (not global scopes) for transparency.
 * Call forWorkspace() on queries to filter by the current workspace.
 */
trait BelongsToWorkspace
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scope query to the given workspace (or the current one).
     */
    public function scopeForWorkspace(Builder $query, ?Workspace $workspace = null): Builder
    {
        if (! $workspace && ! app()->bound('currentWorkspace')) {
            throw new \RuntimeException('No workspace context. Ensure ResolveWorkspace middleware is active.');
        }

        $ws = $workspace ?? app('currentWorkspace');

        return $query->where($this->getTable().'.workspace_id', $ws->id);
    }
}
