<?php

namespace App\Domain\Vfs\Core;

/**
 * Small text helpers used by VFS command primitives.
 *
 * Also owns the stable, agent-facing truncation line formats. Their text is
 * a public contract (agents and Stream B's vfs_read parse "pass cursor" and
 * "[truncated at N bytes of M"), so change them only additively.
 */
class VfsText
{
    /** The visible line appended to display reads that were cut at maxBytes. */
    public const TRUNCATION_SUFFIX = "\n[... truncated]";

    /**
     * Fit one served file body to the budget's read mode.
     *
     * Display budgets truncate with the visible suffix and record the
     * truncation on the budget. Content budgets never truncate: they return
     * the whole body or throw invalid_request when it exceeds the configured
     * content ceiling, so a read-modify-write can never persist a prefix.
     *
     * @throws VfsError invalid_request when a content read exceeds the ceiling
     */
    public static function fit(string $content, VfsBudget $budget, string $path): string
    {
        if ($budget->contentCeiling !== null) {
            if (strlen($content) > $budget->contentCeiling) {
                throw VfsError::invalid("{$path} is too large to edit through the VFS (".strlen($content)." bytes; limit {$budget->contentCeiling}).", [
                    'path' => $path,
                    'bytes' => strlen($content),
                    'max_content_bytes' => $budget->contentCeiling,
                    'hint' => 'Edit a smaller file, or split this content into several files.',
                ]);
            }

            return $content;
        }

        if (strlen($content) > $budget->maxBytes) {
            $budget->recordTruncation();
        }

        return self::truncate($content, $budget->maxBytes);
    }

    /**
     * Whether $content carries the display truncation line produced by a
     * budget-truncated read. Used to refuse persisting display output.
     */
    public static function hasTruncationMarker(string $content): bool
    {
        return str_contains($content, self::TRUNCATION_SUFFIX);
    }

    public static function truncate(string $content, int $maxBytes, string $suffix = self::TRUNCATION_SUFFIX): string
    {
        if (strlen($content) <= $maxBytes) {
            return $content;
        }

        if (strlen($suffix) >= $maxBytes) {
            return substr($suffix, 0, $maxBytes);
        }

        return substr($content, 0, max(0, $maxBytes - strlen($suffix))).$suffix;
    }

    /**
     * Stable last line for a listing cut at a budget. With a cursor the agent
     * can continue exactly where the listing stopped.
     */
    public static function listingTruncationLine(int $shown, string $noun, ?string $cursor, string $budgetName = 'maxEntries'): string
    {
        $more = $shown + 1;
        if ($cursor !== null) {
            return "[truncated: showed {$shown} of ≥{$more} {$noun} — pass cursor \"{$cursor}\" or raise {$budgetName} / narrow the path]";
        }

        return "[truncated: showed {$shown} of ≥{$more} {$noun} — raise {$budgetName} or narrow the path]";
    }

    /**
     * Stable last line for a display read cut at maxBytes.
     */
    public static function readTruncationLine(int $shownBytes, ?int $totalBytes): string
    {
        $total = $totalBytes === null ? 'more' : (string) $totalBytes;

        return "[truncated at {$shownBytes} bytes of {$total} — use vfs_read with offset or head/tail/sed -n]";
    }

    /**
     * Stable last line when a command line's aggregate stdout hit the cap.
     */
    public static function outputTruncationLine(int $shownBytes, int $totalBytes): string
    {
        return "[truncated: output exceeded {$shownBytes} bytes ({$totalBytes} total) — narrow the command, pipe through head, or redirect to a /files path]";
    }

    /**
     * Whether text contains one of the stable truncation lines above or the
     * display-read suffix. Used to refuse persisting display output.
     */
    public static function hasAnyTruncationLine(string $content): bool
    {
        return self::hasTruncationMarker($content)
            || preg_match('/^\[truncated(?: at \d+ bytes of |: (?:showed|output exceeded) )/m', $content) === 1;
    }

    /**
     * @return list<string>
     */
    public static function lines(string $content): array
    {
        if ($content === '') {
            return [];
        }

        return preg_split('/\R/', $content) ?: [];
    }
}
