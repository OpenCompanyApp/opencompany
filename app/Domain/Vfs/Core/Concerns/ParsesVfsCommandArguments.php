<?php

namespace App\Domain\Vfs\Core\Concerns;

/**
 * Composite argv parsing concern for VFS commands.
 *
 * Path expansion and option validation are intentionally split into separate
 * traits because both surfaces tend to grow when command coverage improves.
 */
trait ParsesVfsCommandArguments
{
    use ParsesVfsCommandOptions;
    use ParsesVfsPathArguments;
}
