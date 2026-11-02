<?php

namespace App\Domain\Vfs\Core;

/**
 * One cursor-addressable chunk of a generated VFS collection file.
 *
 * Ordinary files still produce a single terminal page. Collection-backed
 * exports use this value so every returned chunk remains syntactically valid
 * while callers receive an authenticated continuation cursor instead of a
 * silently truncated transcript, NDJSON export, or workspace roster.
 */
final class VfsReadPage
{
    public function __construct(
        public readonly string $content,
        public readonly bool $truncated,
        public readonly ?string $nextCursor,
        public readonly int $seenBefore,
        public readonly int $returned,
        public readonly int $scanned,
        public readonly int $skipped,
    ) {}
}
