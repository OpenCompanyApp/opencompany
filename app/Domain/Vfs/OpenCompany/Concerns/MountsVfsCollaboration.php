<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

/**
 * Composite collaboration mounts for OpenCompanyVfs.
 *
 * Channels and tables share the top-level "collaboration data" area in the
 * public VFS tree, but their route shapes and permissions differ enough that
 * each mount family lives in its own trait.
 */
trait MountsVfsCollaboration
{
    use MountsVfsChannels;
    use MountsVfsTables;
}
