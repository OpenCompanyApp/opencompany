<?php

use App\Models\Workspace;

if (! function_exists('workspace')) {
    /** Get the current workspace instance. */
    function workspace(): Workspace
    {
        return app('currentWorkspace');
    }
}

if (! function_exists('safeBroadcast')) {
    /**
     * Broadcast an event, swallowing and logging any failures.
     *
     * Broadcasting is non-critical — a failed broadcast should never
     * cause a job to retry or an HTTP request to error.
     */
    function safeBroadcast(object $event, string $label = 'event'): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to broadcast {$label}", ['error' => $e->getMessage()]);
        }
    }
}
