<?php

namespace App\Domain\Vfs\OpenCompany\Approvals;

/**
 * Capped unified diff for approval previews.
 *
 * Approvers need to see what a text change does, not a perfect minimal diff.
 * This trims the common leading and trailing lines and emits the changed
 * middle as one hunk with up to three lines of context, which is a valid
 * unified diff for any pair of texts. Large multi-site edits produce one
 * larger hunk instead of several small ones; the byte cap keeps the stored
 * approval row and the agent-facing envelope small.
 *
 * Pure function: no I/O, no workspace access. Stream A owns the LCS shell
 * `diff`; this deliberately does not depend on it so approval previews keep
 * working whatever the shell's diff options become.
 */
final class VfsApprovalDiff
{
    public const DEFAULT_MAX_BYTES = 4000;

    /**
     * @return array{diff: string, truncated: bool, added: int, removed: int}
     */
    public static function unified(string $path, string $before, string $after, int $context = 3, int $maxBytes = self::DEFAULT_MAX_BYTES): array
    {
        $old = self::lines($before);
        $new = self::lines($after);

        $prefix = 0;
        $limit = min(count($old), count($new));
        while ($prefix < $limit && $old[$prefix] === $new[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while ($suffix < $limit - $prefix
            && $old[count($old) - 1 - $suffix] === $new[count($new) - 1 - $suffix]) {
            $suffix++;
        }

        $removed = array_slice($old, $prefix, count($old) - $prefix - $suffix);
        $added = array_slice($new, $prefix, count($new) - $prefix - $suffix);

        if ($removed === [] && $added === []) {
            return ['diff' => '', 'truncated' => false, 'added' => 0, 'removed' => 0];
        }

        $leading = array_slice($old, max(0, $prefix - $context), min($context, $prefix));
        $trailing = array_slice($old, count($old) - $suffix, min($context, $suffix));
        $oldStart = $prefix - count($leading) + 1;
        $newStart = $oldStart;
        $oldCount = count($leading) + count($removed) + count($trailing);
        $newCount = count($leading) + count($added) + count($trailing);

        $body = ["--- a{$path}", "+++ b{$path}", sprintf(
            '@@ -%s +%s @@',
            self::range($oldStart, $oldCount),
            self::range($newStart, $newCount),
        )];
        foreach ($leading as $line) {
            $body[] = ' '.$line;
        }
        foreach ($removed as $line) {
            $body[] = '-'.$line;
        }
        foreach ($added as $line) {
            $body[] = '+'.$line;
        }
        foreach ($trailing as $line) {
            $body[] = ' '.$line;
        }

        $diff = implode("\n", $body)."\n";
        $truncated = false;
        if (strlen($diff) > $maxBytes) {
            $diff = mb_strcut($diff, 0, $maxBytes, 'UTF-8')."\n[... diff truncated]\n";
            $truncated = true;
        }

        return ['diff' => $diff, 'truncated' => $truncated, 'added' => count($added), 'removed' => count($removed)];
    }

    /**
     * Short single-value rendering for field before/after previews.
     */
    public static function value(mixed $value, int $max = 200): mixed
    {
        if (is_string($value) && mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max).'…';
        }
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';

            return mb_strlen($encoded) > $max ? mb_substr($encoded, 0, $max).'…' : $value;
        }

        return $value;
    }

    /** @return list<string> */
    private static function lines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return explode("\n", rtrim($text, "\n"));
    }

    private static function range(int $start, int $count): string
    {
        if ($count === 0) {
            return ($start - 1).',0';
        }

        return $count === 1 ? (string) $start : "{$start},{$count}";
    }
}
