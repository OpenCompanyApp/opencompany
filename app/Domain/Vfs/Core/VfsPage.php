<?php

namespace App\Domain\Vfs\Core;

/**
 * One permission-filtered page returned by a VFS listing adapter.
 *
 * The page deliberately distinguishes returned entries from scanned backend
 * rows. An adapter may need to advance over inaccessible rows before it can
 * fill the visible page, and callers need that distinction for honest
 * truncation and diagnostic metadata.
 */
final class VfsPage
{
    /**
     * @param  list<VfsEntry>  $entries
     */
    public function __construct(
        public readonly array $entries,
        public readonly bool $truncated,
        public readonly ?string $nextCursor,
        public readonly int $seenBefore,
        public readonly int $scanned,
        public readonly int $skipped,
    ) {}
}
