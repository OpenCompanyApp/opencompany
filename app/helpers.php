<?php

use App\Models\Workspace;

if (! function_exists('workspace')) {
    /** Get the current workspace instance. */
    function workspace(): Workspace
    {
        return app('currentWorkspace');
    }
}
