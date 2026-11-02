<?php

namespace App\Domain\Vfs\Core;

/**
 * Internal control-flow signal for VfsAwkProgram (next, exit, break, continue).
 *
 * Never escapes the interpreter: VfsAwkProgram::run() converts every signal
 * into a result or a VfsError.
 *
 * @internal
 */
final class VfsAwkSignal extends \RuntimeException
{
    public function __construct(public readonly string $type, public readonly int $exitCode = 0)
    {
        parent::__construct("awk {$type}");
    }
}
