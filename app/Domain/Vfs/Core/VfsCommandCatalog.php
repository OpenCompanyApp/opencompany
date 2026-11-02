<?php

namespace App\Domain\Vfs\Core;

/**
 * Discoverable catalog for unix-like commands implemented by the VFS engine.
 *
 * Runtime tools expose this through `/tools/vfs/commands.json`, and command
 * discovery helpers such as `which`, `type`, and `command -v` intentionally use
 * the same source so agent-visible support cannot drift from implementation.
 */
final class VfsCommandCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return array_map(
            fn (VfsCommandSpec $spec): array => $spec->catalogEntry(),
            VfsCommandSpecRegistry::all(),
        );
    }
}
