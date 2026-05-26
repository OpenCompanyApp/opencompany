<?php

namespace App\Domain\Vfs\Core;

/**
 * Per-operation limits for VFS reads and searches.
 *
 * Budgets are deliberately conservative because a virtual folder may represent
 * database tables, remote storage, or generated projections rather than a small
 * local directory.
 */
class VfsBudget
{
    public int $maxEntries;

    public int $maxDepth;

    public int $maxBytes;

    public int $maxFiles;

    public int $maxMatches;

    public int $maxLineLength;

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

    private function positive(int $value, int $default, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        return min($value, $max);
    }
}
