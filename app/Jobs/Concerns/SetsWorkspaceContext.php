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

    /**
     * Run $callback with $workspace bound as `currentWorkspace`, then restore
     * whatever was bound before (or nothing), also when it throws.
     *
     * Use this in jobs that may run synchronously inside a request or another
     * job (sync queue, dispatchSync, tests): clearWorkspaceContext() would
     * unbind the caller's workspace, and a bare instance() call leaks this
     * job's workspace into the caller (the VFS fails closed on such a
     * mismatch, see OpenCompanyVfs::assertBoundWorkspace). A null workspace
     * leaves the current binding untouched for the callback.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    protected function withWorkspaceBinding(?Workspace $workspace, \Closure $callback): mixed
    {
        $container = app();
        $previous = $container->bound('currentWorkspace') ? $container->make('currentWorkspace') : null;

        if ($workspace !== null) {
            $container->instance('currentWorkspace', $workspace);
        }

        try {
            return $callback();
        } finally {
            if ($workspace !== null) {
                $previous instanceof Workspace
                    ? $container->instance('currentWorkspace', $previous)
                    : $container->forgetInstance('currentWorkspace');
            }
        }
    }
}
