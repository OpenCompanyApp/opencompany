<?php

namespace App\Domain\Vfs\Core;

/**
 * Exact-match search/replace edit for VFS text content.
 *
 * Owns parsing of the two text patch forms accepted by vfs_patch and the
 * matching rules every text edit must follow. It is pure: it never reads or
 * writes storage, so callers apply it to the content they hold under their own
 * row lock (see MutatesVfsDocuments / MutatesVfsFiles).
 *
 * Matching contract (later phases' vfs_edit builds on this, keep it stable):
 *
 * - The search text is matched literally and byte-exactly; occurrences are
 *   counted non-overlapping from the start.
 * - 0 matches: invalid_request "Patch search text was not found in {path}"
 *   with context {path, hint}.
 * - More than 1 match without replace_all: ambiguous_match with the 1-based
 *   start line of each match. Nothing is replaced.
 * - replace_all: every non-overlapping match is replaced.
 * - Content-anchored staleness (applyAgainstBase): when the caller's base
 *   version is stale but the anchor still resolves unambiguously in the
 *   current content, the edit applies and the result is flagged stale_base.
 *   Otherwise a stale base is a conflict. Only text edits get this leniency;
 *   whole-content overwrites and structured row patches stay strict.
 */
final class VfsTextPatch
{
    /** Bound on line numbers reported in an ambiguous_match context. */
    private const MAX_REPORTED_LINES = 50;

    public function __construct(
        public readonly string $search,
        public readonly string $replace,
        public readonly bool $replaceAll = false,
    ) {}

    /**
     * Parse JSON {"search", "replace", "replace_all"?} or a
     * <<<<<<< SEARCH / ======= / >>>>>>> REPLACE block.
     *
     * @throws VfsError invalid_request for an unsupported format or empty search
     */
    public static function parse(string $patch): self
    {
        $decoded = json_decode($patch, true);
        if (is_array($decoded) && array_key_exists('search', $decoded) && array_key_exists('replace', $decoded)) {
            $search = (string) $decoded['search'];
            if ($search === '') {
                throw VfsError::invalid('Patch search text must not be empty.', [
                    'field' => 'search',
                    'hint' => 'Copy the exact text you want to replace from a fresh read.',
                ]);
            }

            return new self($search, (string) $decoded['replace'], self::truthy($decoded['replace_all'] ?? $decoded['replaceAll'] ?? false));
        }

        if (preg_match('/^<<<<<<< SEARCH\R(?<search>.*?)\R=======\R(?<replace>.*?)\R>>>>>>> REPLACE$/s', trim($patch), $matches) === 1) {
            if ($matches['search'] === '') {
                throw VfsError::invalid('Patch search block must not be empty.', ['field' => 'search']);
            }

            return new self($matches['search'], $matches['replace']);
        }

        throw VfsError::invalid('Unsupported patch format. Use JSON {"search":"old","replace":"new"} (optionally "replace_all": true) or a SEARCH/REPLACE block.');
    }

    /**
     * Byte offsets of each non-overlapping match, in order.
     *
     * @return list<int>
     */
    public function matchOffsets(string $content): array
    {
        $offsets = [];
        $length = strlen($this->search);
        $offset = 0;
        while (($found = strpos($content, $this->search, $offset)) !== false) {
            $offsets[] = $found;
            $offset = $found + $length;
        }

        return $offsets;
    }

    /**
     * Apply the edit to $current, enforcing the uniqueness rules.
     *
     * @return array{content: string, replacements: int}
     *
     * @throws VfsError invalid_request (no match) or ambiguous_match
     */
    public function apply(string $current, string $path): array
    {
        $offsets = $this->matchOffsets($current);
        $count = count($offsets);

        if ($count === 0) {
            throw VfsError::invalid("Patch search text was not found in {$path}", [
                'path' => $path,
                'hint' => 'Read the file again and copy the exact text to replace, including whitespace and line breaks.',
            ]);
        }

        if ($count > 1 && ! $this->replaceAll) {
            throw VfsError::ambiguousMatch($path, $count, $this->lineNumbers($current, $offsets));
        }

        $replaceOffsets = $this->replaceAll ? $offsets : [$offsets[0]];
        $length = strlen($this->search);
        $next = '';
        $cursor = 0;
        foreach ($replaceOffsets as $start) {
            $next .= substr($current, $cursor, $start - $cursor).$this->replace;
            $cursor = $start + $length;
        }
        $next .= substr($current, $cursor);

        return ['content' => $next, 'replacements' => count($replaceOffsets)];
    }

    /**
     * Apply against the caller's base version with content-anchored staleness.
     *
     * $currentVersion must be computed from $current under the caller's lock.
     * A null $givenVersion means "no concurrency check requested".
     *
     * @return array{content: string, replacements: int, stale_base: bool}
     *
     * @throws VfsError conflict when the base is stale and the anchor no longer
     *                  resolves unambiguously; otherwise as apply()
     */
    public function applyAgainstBase(string $current, string $path, ?string $currentVersion, ?string $givenVersion): array
    {
        $stale = $givenVersion !== null && $currentVersion !== null && ! hash_equals($currentVersion, $givenVersion);

        if ($stale) {
            $count = count($this->matchOffsets($current));
            if ($count === 0 || ($count > 1 && ! $this->replaceAll)) {
                throw VfsError::conflict(
                    $path,
                    $currentVersion,
                    $givenVersion,
                    'The content changed since your read and your search text no longer matches exactly once. Read it again and retry with the current text.',
                    ['matches' => $count],
                );
            }
        }

        return [...$this->apply($current, $path), 'stale_base' => $stale];
    }

    /**
     * @param  list<int>  $offsets
     * @return list<int>
     */
    private function lineNumbers(string $content, array $offsets): array
    {
        $lines = [];
        foreach (array_slice($offsets, 0, self::MAX_REPORTED_LINES) as $offset) {
            $lines[] = substr_count($content, "\n", 0, $offset) + 1;
        }

        return $lines;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'yes', 'on'], true);
    }
}
