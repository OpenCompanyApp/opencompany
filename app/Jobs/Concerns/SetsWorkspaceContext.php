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
        if (! $workspaceId || ! ($workspace = Workspace::find($workspaceId))) {
            throw new \RuntimeException('Queued agent work has no valid workspace context.');
        }

        app()->instance('currentWorkspace', $workspace);
    }

    /**
     * Queue workers reuse the application container across jobs; never leave a
     * previous tenant bound after this job completes or fails.
     */
    protected function clearWorkspaceContext(): void
    {
        app()->forgetInstance('currentWorkspace');
    }
}
