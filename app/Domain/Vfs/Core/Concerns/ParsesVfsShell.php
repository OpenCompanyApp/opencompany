<?php

namespace App\Domain\Vfs\Core\Concerns;

/**
 * Composite shell parsing concern for the VFS command executor.
 *
 * The syntax and argv helpers stay separate so command behavior can grow
 * without turning parser support into another monolithic file.
 */
trait ParsesVfsShell
{
    use ParsesVfsCommandArguments;
    use ParsesVfsRedirections;
    use ParsesVfsShellSyntax;
}
