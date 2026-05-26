<?php

namespace App\Domain\Vfs\Core;

/**
 * Small text helpers used by VFS command primitives.
 */
class VfsText
{
    public static function truncate(string $content, int $maxBytes, string $suffix = "\n[... truncated]"): string
    {
        if (strlen($content) <= $maxBytes) {
            return $content;
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
