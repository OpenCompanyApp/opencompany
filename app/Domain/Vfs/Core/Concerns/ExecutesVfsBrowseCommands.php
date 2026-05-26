<?php

namespace App\Domain\Vfs\Core\Concerns;

/**
 * Composite browse and discovery commands for the VFS shell executor.
 *
 * `ls`/`cd` and `tree` have separate implementation concerns because their
 * budget and recursion behavior evolves independently.
 */
trait ExecutesVfsBrowseCommands
{
    use ExecutesVfsListCommands;
    use ExecutesVfsTreeCommands;
}
