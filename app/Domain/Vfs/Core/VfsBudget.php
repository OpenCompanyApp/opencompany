<?php

namespace App\Domain\Vfs\Core;

/**
 * Per-operation limits for VFS reads and searches.
 *
 * Budgets are deliberately conservative because a virtual folder may represent
 * database tables, remote storage, or generated projections rather than a small
 * local directory.
 *
 * Two read modes share this object:
 *
 * - Display reads (the default) cap file bodies at maxBytes and may end with
 *   the visible VfsText truncation line. Agents need that line to know they
 *   saw a prefix, so it stays.
 * - Content reads (VfsBudget::forContent, used by OpenCompanyVfs::readContent)
 *   return the full body or fail with invalid_request above contentCeiling.
 *   They are the read half of every read-modify-write path. Invariant: content
 *   that may be written back must come from a content read, never a display
 *   read.
 *
 * The budget also carries per-operation read observations (truncations, last
 * served version) because it is the one context object every mount already
 * receives. Observations are advisory side channels for the caller that
 * created the budget; they never change authorization.
 */
class VfsBudget
{
    public int $maxEntries;

    public int $maxDepth;

    public int $maxBytes;

    public int $maxFiles;

    public int $maxMatches;

    public int $maxLineLength;

    /**
     * Hard byte ceiling for content reads; null for display reads.
     */
    public ?int $contentCeiling = null;

    /** Number of display reads this budget truncated (see recordTruncation). */
    private int $truncations = 0;

    /** Version token of the most recent single object served through this budget. */
    private ?string $lastReadVersion = null;

    /** Canonical (by-id) path of the most recent single object served through this budget. */
    private ?string $lastCanonicalPath = null;

    public function __construct(
        int $maxEntries = 100,
        int $maxDepth = 3,
        int $maxBytes = 100_000,
        int $maxFiles = 100,
        int $maxMatches = 100,
        int $maxLineLength = 2_000,
    ) {
        $this->maxEntries = $this->positive($maxEntries, 100, 1, 1_000);
        $this->maxDepth = $this->positive($maxDepth, 3, 0, 25);
        $this->maxBytes = $this->positive($maxBytes, 100_000, 1, 2_000_000);
        $this->maxFiles = $this->positive($maxFiles, 100, 1, 2_000);
        $this->maxMatches = $this->positive($maxMatches, 100, 1, 2_000);
        $this->maxLineLength = $this->positive($maxLineLength, 2_000, 80, 20_000);
    }

    /**
     * Build a content-read budget: full bodies, no truncation line, and a
     * hard failure above $ceiling. Listing limits keep their defaults so a
     * content read of a generated collection stays bounded.
     */
    public static function forContent(int $ceiling): self
    {
        $budget = new self;
        $budget->contentCeiling = max(1, $ceiling);

        return $budget;
    }

    public function isContentRead(): bool
    {
        return $this->contentCeiling !== null;
    }

    /** Called by VfsText::fit when a display read dropped bytes. */
    public function recordTruncation(): void
    {
        $this->truncations++;
    }

    public function truncations(): int
    {
        return $this->truncations;
    }

    /**
     * Record the version token computed from the exact bytes a mount served,
     * so a caller (vfs_read) can report a version that matches the content it
     * returned instead of issuing a second, racy stat.
     */
    public function recordReadVersion(?string $version): void
    {
        $this->lastReadVersion = $version;
    }

    public function lastReadVersion(): ?string
    {
        return $this->lastReadVersion;
    }

    /**
     * Mounts record the canonical path of the object they served, so read
     * callers can report it without a second stat (Stream C1 addition).
     */
    public function recordCanonicalPath(?string $canonicalPath): void
    {
        $this->lastCanonicalPath = $canonicalPath;
    }

    public function lastCanonicalPath(): ?string
    {
        return $this->lastCanonicalPath;
    }

    private function positive(int $value, int $default, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        return min($value, $max);
    }
}
