<?php

namespace App\Domain\Vfs\Core\Concerns;

/**
 * Composite shell syntax concern for VfsCommandExecutor.
 *
 * The public executor consumes this trait, while guard, grouping, and argv
 * tokenization details live in focused traits to keep parser growth contained.
 */
trait ParsesVfsShellSyntax
{
    use GuardsVfsShellSyntax;
    use ParsesVfsCommandGroups;
    use ParsesVfsCommandTokens;
}
