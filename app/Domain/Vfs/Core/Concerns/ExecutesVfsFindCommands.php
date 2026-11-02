<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use Carbon\Carbon;

/**
 * `find` command support for bounded VFS traversal.
 *
 * This stays separate from directory rendering commands because it owns the
 * traversal budget semantics agents rely on for very large virtual folders.
 */
trait ExecutesVfsFindCommands
{
    private function find(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'find');
        $path = $this->firstPathArg($tokens, $cwd) ?? $cwd;
        $type = $this->optionValue($tokens, ['-type']);
        if ($this->optionWasProvided($tokens, ['-type']) && ! in_array($type, ['f', 'd', 'file', 'directory'], true)) {
            throw VfsError::invalid('find -type supports only f/file or d/directory.');
        }

        $maxDepth = $this->integerOptionValue($tokens, ['-maxdepth', '-maxDepth', '--max-depth'], 'find', 'max depth');
        $minDepth = $this->integerOptionValue($tokens, ['-mindepth'], 'find', 'min depth') ?? 0;
        $matcher = $this->findMatcher($tokens, $path);
        if ($maxDepth !== null) {
            $budget = new VfsBudget($budget->maxEntries, max(0, $maxDepth), $budget->maxBytes, $budget->maxFiles, $budget->maxMatches, $budget->maxLineLength);
        }
        $lines = [];
        $seen = [];
        $scanned = 0;
        $truncated = false;
        $skipped = 0;
        $this->appendFind($agent, $path, $budget, $lines, $seen, $matcher, $minDepth, in_array('-ls', $tokens, true), 0, $scanned, $truncated, $skipped);
        $separator = in_array('-print0', $tokens, true) ? "\0" : "\n";

        return [
            'stdout' => implode($separator, $lines),
            'cwd' => $cwd,
            'class' => 'browse',
            'returned' => count($lines),
            'limit' => $budget->maxEntries,
            'scanned' => $scanned,
            'max_files' => $budget->maxFiles,
            'skipped' => $skipped,
            'truncated' => $truncated || count($lines) >= $budget->maxEntries || $scanned >= $budget->maxFiles,
        ];
    }

    /**
     * @param  callable(array{name: string, path: string, type: string, size: int, mtime_days: ?int, empty: bool, content_format?: string}): bool  $matcher
     */
    private function appendFind(User $agent, string $path, VfsBudget $budget, array &$lines, array &$seen, callable $matcher, int $minDepth, bool $lsFormat, int $depth, int &$scanned, bool &$truncated, int &$skipped): void
    {
        if ($depth > $budget->maxDepth || count($lines) >= $budget->maxEntries || $scanned >= $budget->maxFiles) {
            $truncated = $depth > $budget->maxDepth || count($lines) >= $budget->maxEntries || $scanned >= $budget->maxFiles;

            return;
        }

        $listBudget = new VfsBudget(
            min(max($budget->maxEntries, $budget->maxFiles), $budget->maxFiles),
            $budget->maxDepth,
            $budget->maxBytes,
            $budget->maxFiles,
            $budget->maxMatches,
            $budget->maxLineLength,
        );

        try {
            $stat = $this->vfs->stat($agent, $path);
            $scanned++;
            $pathName = basename($path) === '' ? '/' : basename($path);
            $statType = (string) ($stat['type'] ?? 'directory');
            if ($depth >= $minDepth && $matcher($this->findCandidate($agent, $pathName, $path, $statType, $stat, $budget)) && ! isset($seen[$path])) {
                $lines[] = $lsFormat ? $this->formatFindLs($stat, $path) : $path;
                $seen[$path] = true;
            }
            if ($depth >= $budget->maxDepth || count($lines) >= $budget->maxEntries || $scanned >= $budget->maxFiles) {
                $truncated = count($lines) >= $budget->maxEntries || $scanned >= $budget->maxFiles;

                return;
            }

            $entries = $this->vfs->listBounded($agent, $path, $listBudget);
        } catch (VfsError) {
            $skipped++;

            return;
        }

        foreach ($entries as $entry) {
            if ($scanned >= $budget->maxFiles) {
                $truncated = true;

                return;
            }
            $scanned++;
            $entryPath = $entry->canonicalPath ?? $entry->path;
            if ($depth + 1 >= $minDepth && $matcher($this->findCandidate($agent, $entry->name, $entryPath, $entry->type, $entry->jsonSerialize(), $budget))) {
                if (! isset($seen[$entryPath])) {
                    $lines[] = $lsFormat ? sprintf('%s %s %s', $entry->type === 'directory' ? 'd' : '-', $entry->version ?? '-', $entryPath) : $entryPath;
                    $seen[$entryPath] = true;
                    if (count($lines) >= $budget->maxEntries) {
                        $truncated = true;

                        return;
                    }
                }
            }
            if ($entry->type === 'directory') {
                $this->appendFind($agent, $entryPath, $budget, $lines, $seen, $matcher, $minDepth, $lsFormat, $depth + 1, $scanned, $truncated, $skipped);
            }
        }
    }

    /**
     * @return callable(array{name: string, path: string, type: string, size: int, mtime_days: ?int, empty: bool, content_format?: string}): bool
     */
    private function findMatcher(array $tokens, string $startPath): callable
    {
        $expression = $this->findExpressionTokens($tokens, $startPath);
        if ($expression === []) {
            return fn (array $candidate): bool => true;
        }

        $index = 0;
        $matcher = $this->parseFindOr($expression, $index);
        if ($index !== count($expression)) {
            throw VfsError::invalid('Invalid find expression.');
        }

        return $matcher;
    }

    /**
     * @param  list<string>  $expression
     * @return callable(array{name: string, path: string, type: string, size: int, mtime_days: ?int, empty: bool, content_format?: string}): bool
     */
    private function parseFindOr(array $expression, int &$index): callable
    {
        $left = $this->parseFindAnd($expression, $index);
        while (in_array($expression[$index] ?? null, ['-o', '-or'], true)) {
            $index++;
            $right = $this->parseFindAnd($expression, $index);
            $left = fn (array $candidate): bool => $left($candidate) || $right($candidate);
        }

        return $left;
    }

    /**
     * @param  list<string>  $expression
     * @return callable(array{name: string, path: string, type: string, size: int, mtime_days: ?int, empty: bool, content_format?: string}): bool
     */
    private function parseFindAnd(array $expression, int &$index): callable
    {
        $left = $this->parseFindNot($expression, $index);
        while (($expression[$index] ?? null) !== null && ! in_array($expression[$index], ['-o', '-or'], true)) {
            if (in_array($expression[$index], ['-a', '-and'], true)) {
                $index++;
            }
            $right = $this->parseFindNot($expression, $index);
            $left = fn (array $candidate): bool => $left($candidate) && $right($candidate);
        }

        return $left;
    }

    /**
     * @param  list<string>  $expression
     * @return callable(array{name: string, path: string, type: string, size: int, mtime_days: ?int, empty: bool, content_format?: string}): bool
     */
    private function parseFindNot(array $expression, int &$index): callable
    {
        if (($expression[$index] ?? null) === '-not') {
            $index++;
            $predicate = $this->parseFindNot($expression, $index);

            return fn (array $candidate): bool => ! $predicate($candidate);
        }

        return $this->parseFindPredicate($expression, $index);
    }

    /**
     * @param  list<string>  $expression
     * @return callable(array{name: string, path: string, type: string, size: int, mtime_days: ?int, empty: bool, content_format?: string}): bool
     */
    private function parseFindPredicate(array $expression, int &$index): callable
    {
        $token = $this->nextFindExpressionToken($expression, $index, 'predicate');

        return match ($token) {
            '-name' => $this->findStringPredicate($this->nextFindExpressionToken($expression, $index, '-name'), false, 'name'),
            '-iname' => $this->findStringPredicate($this->nextFindExpressionToken($expression, $index, '-iname'), true, 'name'),
            '-path' => $this->findStringPredicate($this->nextFindExpressionToken($expression, $index, '-path'), false, 'path'),
            '-type' => $this->findTypePredicate($this->nextFindExpressionToken($expression, $index, '-type')),
            '-empty' => fn (array $candidate): bool => $candidate['empty'],
            '-size' => $this->findNumericPredicate($this->nextFindExpressionToken($expression, $index, '-size'), 'size'),
            '-mtime' => $this->findNumericPredicate($this->nextFindExpressionToken($expression, $index, '-mtime'), 'mtime_days'),
            '-print', '-print0', '-ls' => fn (array $candidate): bool => true,
            default => throw VfsError::invalid("Unsupported find expression token: {$token}"),
        };
    }

    /**
     * @param  list<string>  $expression
     */
    private function nextFindExpressionToken(array $expression, int &$index, string $context): string
    {
        if (! array_key_exists($index, $expression)) {
            throw VfsError::invalid("find {$context} requires another token.");
        }

        return $expression[$index++];
    }

    /**
     * @return list<string>
     */
    private function findExpressionTokens(array $tokens, string $startPath): array
    {
        $out = [];
        $pathRemoved = false;
        $valueControls = ['-maxdepth', '-maxDepth', '--max-depth', '-mindepth'];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token, $valueControls, true)) {
                $i++;

                continue;
            }
            if (str_starts_with($token, '--max-depth=')) {
                continue;
            }
            if (! $pathRemoved && ! str_starts_with($token, '-') && $this->path($token, dirname($startPath) === '.' ? '/' : dirname($startPath)) === $startPath) {
                $pathRemoved = true;

                continue;
            }
            $out[] = $token;
        }

        return $out;
    }

    private function findStringPredicate(?string $pattern, bool $ignoreCase, string $field): callable
    {
        if ($pattern === null || $pattern === '') {
            throw VfsError::invalid("find -{$field} requires a pattern.");
        }

        return function (array $candidate) use ($pattern, $ignoreCase, $field): bool {
            if ($field === 'name' && $pattern === '*.md' && $candidate['type'] === 'file' && (($candidate['content_format'] ?? null) === 'markdown')) {
                return true;
            }

            return $ignoreCase
                ? fnmatch(strtolower($pattern), strtolower((string) $candidate[$field]))
                : fnmatch($pattern, (string) $candidate[$field]);
        };
    }

    private function findTypePredicate(?string $type): callable
    {
        if (! in_array($type, ['f', 'd', 'file', 'directory'], true)) {
            throw VfsError::invalid('find -type supports only f/file or d/directory.');
        }

        return fn (array $candidate): bool => ($type === 'f' || $type === 'file')
            ? $candidate['type'] === 'file'
            : $candidate['type'] === 'directory';
    }

    private function findNumericPredicate(?string $expr, string $field): callable
    {
        if ($expr === null || preg_match('/^([+-]?)(\d+)([cBkKmMgG]?)$/', $expr, $matches) !== 1) {
            throw VfsError::invalid("find {$field} requires a numeric expression.");
        }
        $operator = $matches[1] ?: '=';
        $multiplier = match ($matches[3]) {
            'k', 'K' => 1024,
            'm', 'M' => 1024 * 1024,
            'g', 'G' => 1024 * 1024 * 1024,
            default => 1,
        };
        $expected = (int) $matches[2] * $multiplier;

        return function (array $candidate) use ($field, $operator, $expected): bool {
            $actual = $candidate[$field] ?? null;
            if ($actual === null) {
                return false;
            }

            return match ($operator) {
                '+' => $actual > $expected,
                '-' => $actual < $expected,
                default => $actual === $expected,
            };
        };
    }

    /**
     * @return array{name: string, path: string, type: string, size: int, mtime_days: ?int, empty: bool, content_format?: string}
     */
    private function findCandidate(User $agent, string $name, string $path, string $type, array $stat, VfsBudget $budget): array
    {
        $metadata = $stat['metadata'] ?? [];

        return [
            'name' => $name,
            'path' => $path,
            'type' => $type,
            'size' => $this->findSize($agent, $path, $type, $stat, $budget),
            'mtime_days' => $this->findMtimeDays($stat),
            'empty' => $this->findIsEmpty($agent, $path, $type, $budget),
            'content_format' => is_array($metadata) ? ($metadata['content_format'] ?? null) : null,
        ];
    }

    private function findSize(User $agent, string $path, string $type, array $stat, VfsBudget $budget): int
    {
        $metadata = $stat['metadata'] ?? [];
        if (isset($stat['size']) || isset($metadata['size'])) {
            return (int) ($stat['size'] ?? $metadata['size']);
        }
        if ($type !== 'file') {
            return 0;
        }
        try {
            return strlen($this->vfs->read($agent, $path, $budget));
        } catch (VfsError) {
            return 0;
        }
    }

    private function findMtimeDays(array $stat): ?int
    {
        $updatedAt = $stat['updated_at'] ?? $stat['metadata']['updated_at'] ?? null;
        if (! is_string($updatedAt) || $updatedAt === '') {
            return null;
        }
        try {
            return max(0, now()->diffInDays(Carbon::parse($updatedAt)));
        } catch (\Throwable) {
            return null;
        }
    }

    private function findIsEmpty(User $agent, string $path, string $type, VfsBudget $budget): bool
    {
        try {
            if ($type === 'directory') {
                return $this->vfs->listBounded($agent, $path, new VfsBudget(maxEntries: 1)) === [];
            }

            return $this->vfs->read($agent, $path, $budget) === '';
        } catch (VfsError) {
            return false;
        }
    }

    private function formatFindLs(array $stat, string $path): string
    {
        return sprintf('%s %s %s', ($stat['type'] ?? null) === 'directory' ? 'd' : '-', (string) ($stat['version'] ?? '-'), $path);
    }
}
