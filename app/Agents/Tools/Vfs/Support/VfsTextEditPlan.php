<?php

namespace App\Agents\Tools\Vfs\Support;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsTextPatch;

/**
 * Applies a vfs_edit edit list in memory and reduces it to one anchored patch.
 *
 * Matching is Phase 1's VfsTextPatch engine, unchanged: exact byte match,
 * not-found (invalid_request), ambiguous_match with 1-based lines, replace_all,
 * and content-anchored staleness via applyAgainstBase(). Each edit runs on the
 * output of the previous one; any failure aborts the whole plan (all or
 * nothing) and names the failing edit in context.edit_index.
 *
 * Reduction: the whole edit list becomes ONE VfsTextPatch whose search text is
 * the smallest region of the original content that covers every change and
 * occurs exactly once in it (grown line by line until unique). Applying it to
 * the original reproduces the planned content exactly. vfs_edit sends that
 * patch through OpenCompanyVfs::patch(), so the write, its permission
 * composition, approval request and approved replay are the existing
 * vfs_patch path; a replayed approval re-anchors on that region
 * (content-anchored staleness) instead of overwriting newer content.
 */
final class VfsTextEditPlan
{
    /**
     * @param  list<array{old_text: string, new_text: string, replace_all: bool}>  $edits
     */
    private function __construct(
        public readonly string $original,
        public readonly string $content,
        public readonly int $replacements,
        public readonly bool $staleBase,
        public readonly array $edits,
    ) {}

    /**
     * @param  list<mixed>  $edits  Raw edit objects from the tool request
     *
     * @throws VfsError invalid_request, ambiguous_match or conflict
     */
    public static function build(string $path, string $current, ?string $currentVersion, ?string $givenVersion, array $edits): self
    {
        if ($edits === []) {
            throw VfsError::invalid('edits must contain at least one {old_text, new_text} edit.', ['field' => 'edits', 'path' => $path]);
        }

        $normalized = [];
        $content = $current;
        $replacements = 0;
        $staleBase = false;

        foreach ($edits as $index => $edit) {
            $edit = self::normalizeEdit($edit, $index, $path);
            $normalized[] = $edit;

            try {
                $applied = (new VfsTextPatch($edit['old_text'], $edit['new_text'], $edit['replace_all']))
                    ->applyAgainstBase($content, $path, $currentVersion, $givenVersion);
            } catch (VfsError $e) {
                throw new VfsError($e->errorCode, self::indexedMessage($e, $index), $e->context + ['edit_index' => $index]);
            }

            $content = $applied['content'];
            $replacements += $applied['replacements'];
            $staleBase = $staleBase || $applied['stale_base'];
        }

        return new self($current, $content, $replacements, $staleBase, $normalized);
    }

    public function changed(): bool
    {
        return $this->content !== $this->original;
    }

    /**
     * One search/replace that turns $original into $content, anchored on a
     * region that occurs exactly once in $original.
     *
     * @return array{search: string, replace: string}
     */
    public function anchoredPatch(): array
    {
        $old = $this->original;
        $new = $this->content;
        $oldLength = strlen($old);
        $newLength = strlen($new);

        $prefix = 0;
        $limit = min($oldLength, $newLength);
        while ($prefix < $limit && $old[$prefix] === $new[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        $suffixLimit = $limit - $prefix;
        while ($suffix < $suffixLimit && $old[$oldLength - 1 - $suffix] === $new[$newLength - 1 - $suffix]) {
            $suffix++;
        }

        // Grow [start, end) to whole lines first, then by one line on each
        // side until the search text is non-empty and unique.
        $start = self::lineStart($old, $prefix);
        $end = self::lineEnd($old, $oldLength - $suffix);
        while (true) {
            $search = substr($old, $start, $end - $start);
            if ($search !== '' && self::occursOnce($old, $search)) {
                break;
            }
            if ($start === 0 && $end === $oldLength) {
                break;
            }
            $start = $start > 0 ? self::lineStart($old, $start - 1) : 0;
            $end = $end < $oldLength ? self::lineEnd($old, $end + 1) : $oldLength;
        }

        $delta = $newLength - $oldLength;

        return [
            'search' => substr($old, $start, $end - $start),
            'replace' => substr($new, $start, $end - $start + $delta),
        ];
    }

    /**
     * @return array{old_text: string, new_text: string, replace_all: bool}
     */
    private static function normalizeEdit(mixed $edit, int $index, string $path): array
    {
        if (! is_array($edit)) {
            throw VfsError::invalid("edits[{$index}] must be an object {old_text, new_text, replace_all?}.", ['field' => 'edits', 'edit_index' => $index, 'path' => $path]);
        }

        $old = $edit['old_text'] ?? $edit['oldText'] ?? null;
        $new = $edit['new_text'] ?? $edit['newText'] ?? null;
        if (! is_string($old) || $old === '') {
            throw VfsError::invalid("edits[{$index}].old_text must be non-empty text copied from the file.", [
                'field' => 'old_text',
                'edit_index' => $index,
                'path' => $path,
                'hint' => 'To add text, include a nearby unique line in old_text and repeat it in new_text.',
            ]);
        }
        if (! is_string($new)) {
            throw VfsError::invalid("edits[{$index}].new_text must be text (use \"\" to delete).", ['field' => 'new_text', 'edit_index' => $index, 'path' => $path]);
        }
        if ($old === $new) {
            throw VfsError::invalid("edits[{$index}] has identical old_text and new_text.", ['field' => 'new_text', 'edit_index' => $index, 'path' => $path]);
        }

        $replaceAll = $edit['replace_all'] ?? $edit['replaceAll'] ?? false;

        return [
            'old_text' => $old,
            'new_text' => $new,
            'replace_all' => $replaceAll === true || in_array($replaceAll, [1, '1', 'true'], true),
        ];
    }

    private static function indexedMessage(VfsError $e, int $index): string
    {
        return "edits[{$index}]: ".$e->getMessage();
    }

    private static function occursOnce(string $haystack, string $needle): bool
    {
        $first = strpos($haystack, $needle);

        return $first !== false && strpos($haystack, $needle, $first + 1) === false;
    }

    private static function lineStart(string $text, int $offset): int
    {
        if ($offset <= 0) {
            return 0;
        }
        $newline = strrpos(substr($text, 0, $offset), "\n");

        return $newline === false ? 0 : $newline + 1;
    }

    private static function lineEnd(string $text, int $offset): int
    {
        $length = strlen($text);
        if ($offset >= $length) {
            return $length;
        }
        if ($offset > 0 && $text[$offset - 1] === "\n") {
            return $offset;
        }
        $newline = strpos($text, "\n", max(0, $offset));

        return $newline === false ? $length : $newline + 1;
    }
}
