<?php

namespace App\Jobs\Concerns;

use App\Models\Workspace;

trait SetsWorkspaceContext
{
    /**
     * Bind the workspace into the container so workspace()-scoped
     * queries and services resolve correctly inside queue workers.
     */
    protected function setWorkspaceContext(?string $workspaceId): void
    {
        if ($workspaceId) {
            $workspace = Workspace::find($workspaceId);

            if ($workspace) {
                app()->instance('currentWorkspace', $workspace);
            }
        }
    }
}
