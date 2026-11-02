<?php

namespace App\Domain\Vfs\Core;

/**
 * Small text helpers used by VFS command primitives.
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
