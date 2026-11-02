<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\DataTable;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Support\Str;

/**
 * Public text-search operation for VFS paths (`vfs_rg`, shell grep/rg).
 *
 * Order of work (invariant, fixes candidate starvation):
 *   1. index candidates (VfsTextSearchService) are visited FIRST, in their
 *      deterministic order, then
 *   2. the requested roots are walked live,
 * both under one file budget (maxFiles) and one match budget (maxMatches),
 * de-duplicated by canonical path. Every visited path is stat'ed and read
 * through the normal mounts, so permissions are re-checked per object and a
 * candidate the agent cannot read is only counted as `skipped`.
 *
 * Result contract: {matches, count, scanned, max_files, skipped, truncated,
 * truncated_reason (null|max_matches|max_files), backend}.
 *
 * Path contract (shared with shell grep -r / rg and find, see docs/tools/vfs.md
 * "Paths in search and find output"): a match's `path` is the readable
 * friendly path (the walked path under the requested root; index candidates
 * are mapped to their friendly path where one exists). Friendly names are
 * already unambiguous (`name--1a2b3c4d` suffixes), and objects with no
 * single friendly path (tasks, list items, channel days, docs under system
 * folders) keep their by-id path. `canonical_path` (by-id) is always
 * present for structured callers; visiting and dedupe use it, never the
 * display path. `truncated` is
 * true only when more output is known to exist: one more match was seen past
 * maxMatches, or unscanned files remained when maxFiles ran out.
 *
 * Regex safety: user PCRE runs with a lowered pcre.backtrack_limit and a
 * cumulative time budget; hitting either is an explicit invalid_request
 * (reason regex_too_expensive), never a silent "no match".
 */
trait SearchesVfsContent
{
    /** pcre.backtrack_limit while evaluating agent-supplied regexes. */
    private int $searchRegexBacktrackLimit = 100_000;

    /** Cumulative wall time allowed inside regex evaluation per search. */
    private int $searchRegexTimeBudgetMs = 2_000;

    private float $searchRegexSpentMs = 0.0;

    /**
     * @param  list<string>  $paths
     * @return list<array{path: string, canonical_path?: string, line?: int, snippet: string, match?: string, backend: string}>
     */
    public function search(User $agent, string $pattern, array $paths, ?VfsBudget $budget = null, bool $regex = false, bool $caseInsensitive = false): array
    {
        $this->assertBoundWorkspace($agent);

        return $this->searchWithMetadata($agent, $pattern, $paths, $budget, $regex, $caseInsensitive)['matches'];
    }

    /**
     * @param  list<string>  $paths
     * @return array{matches: list<array{path: string, canonical_path?: string, line?: int, snippet: string, match?: string, backend: string}>, count: int, scanned: int, max_files: int, skipped: int, truncated: bool, truncated_reason: string|null, backend: string}
     */
    public function searchWithMetadata(User $agent, string $pattern, array $paths, ?VfsBudget $budget = null, bool $regex = false, bool $caseInsensitive = false): array
    {
        $this->assertBoundWorkspace($agent);
        $budget ??= new VfsBudget;
        $paths = $paths === [] ? ['/'] : $paths;
        $normalizedPaths = array_values(array_unique(array_map(fn (string $path): string => $this->normalizePath($path), $paths)));
        $expression = '~'.str_replace('~', '\~', $pattern).'~'.($caseInsensitive ? 'i' : '');
        if ($regex) {
            $this->assertValidSearchRegex($expression);
        }

        $candidatePaths = $this->textSearch->candidatePaths(
            $agent,
            $pattern,
            $normalizedPaths,
            $budget,
            $regex,
            $this->searchScopes($agent, $normalizedPaths),
            $this->allowedChannels($agent),
        );
        array_push($candidatePaths, ...$this->mountSearchCandidates($agent, $pattern, $normalizedPaths, $budget, $regex));
        $backend = $candidatePaths !== [] ? 'source_index+live' : 'live';

        $matches = [];
        $scanned = 0;
        $skipped = 0;
        $seenFiles = [];
        $truncatedReason = null;
        $previousBacktrackLimit = ini_get('pcre.backtrack_limit');
        $this->searchRegexSpentMs = 0.0;
        if ($regex) {
            ini_set('pcre.backtrack_limit', (string) $this->searchRegexBacktrackLimit);
        }

        try {
            foreach ($this->searchTargets($agent, $candidatePaths, $normalizedPaths, $budget) as $displayPath => $filePath) {
                try {
                    $stat = $this->stat($agent, $filePath);
                } catch (VfsError) {
                    // Some listings report a canonical path that is not
                    // readable on its own (agent-area documents live in a
                    // system folder, so /docs/by-id/{id} hides them). The
                    // walked display path is the one that resolved: use it.
                    if (! is_string($displayPath) || $displayPath === $filePath) {
                        $skipped++;

                        continue;
                    }
                    try {
                        $stat = $this->stat($agent, $displayPath);
                        $filePath = $displayPath;
                    } catch (VfsError) {
                        $skipped++;

                        continue;
                    }
                }

                $fileKey = (string) ($stat['canonical_path'] ?? $filePath);
                if (isset($seenFiles[$fileKey])) {
                    continue;
                }
                $seenFiles[$fileKey] = true;
                // Candidates carry no display path (int key); map them only
                // after stat proved the agent may see the object.
                $displayPath = is_string($displayPath) ? $displayPath : $this->searchDisplayPath($filePath);

                // Invariant: the file budget is checked before reading, and
                // only when another readable file actually exists, so
                // truncated=max_files means "files were left unscanned".
                if ($scanned >= $budget->maxFiles) {
                    $truncatedReason = 'max_files';

                    break;
                }

                try {
                    $content = $this->read($agent, $filePath, $budget);
                } catch (VfsError) {
                    $skipped++;

                    continue;
                }

                $scanned++;
                foreach (VfsText::lines($content) as $lineNumber => $line) {
                    $lineMatches = $regex
                        ? $this->regexLineMatches($expression, $line, $pattern)
                        : $this->literalLineMatches($pattern, $line, $caseInsensitive);

                    foreach ($lineMatches as $matchedText) {
                        // One match past the budget proves truncation; it is
                        // never returned.
                        if (count($matches) >= $budget->maxMatches) {
                            $truncatedReason = 'max_matches';

                            break 3;
                        }

                        $matches[] = [
                            'path' => $displayPath,
                            'canonical_path' => $fileKey,
                            'line' => $lineNumber + 1,
                            'snippet' => Str::limit($line, $budget->maxLineLength, '...'),
                            'match' => $matchedText,
                            'backend' => $backend,
                        ];
                    }
                }
            }
        } finally {
            if ($regex && $previousBacktrackLimit !== false) {
                ini_set('pcre.backtrack_limit', $previousBacktrackLimit);
            }
        }

        return [
            'matches' => $matches,
            'count' => count($matches),
            'scanned' => $scanned,
            'max_files' => $budget->maxFiles,
            'skipped' => $skipped,
            'truncated' => $truncatedReason !== null,
            'truncated_reason' => $truncatedReason,
            'backend' => $backend,
        ];
    }

    /**
     * Candidates first, then the live walk of each requested root.
     *
     * Yields display path => path to stat/read. Candidates yield an int key
     * (no display path yet, see searchDisplayPath()); walked files yield
     * their friendly path under the requested root => their canonical path.
     *
     * @param  list<string>  $candidatePaths
     * @param  list<string>  $requestedPaths
     * @return \Generator<int|string, string>
     */
    private function searchTargets(User $agent, array $candidatePaths, array $requestedPaths, VfsBudget $budget): \Generator
    {
        foreach ($candidatePaths as $candidate) {
            yield $candidate;
        }

        foreach ($requestedPaths as $path) {
            yield from $this->walkReadableFiles($agent, $path, $budget);
        }
    }

    /**
     * Friendly path of an index candidate (by-id), or the candidate itself
     * when the object has no single friendly path. Only called after stat
     * authorized the candidate. Workspace-scoped lookups; never widens access.
     */
    private function searchDisplayPath(string $candidate): string
    {
        if (preg_match('#^/docs/by-id/([^/]+)$#', $candidate, $m) === 1) {
            $doc = Document::forWorkspace()->find($m[1]);

            return $doc instanceof Document ? $this->documentFriendlyPath($doc) : $candidate;
        }
        if (preg_match('#^/files/by-id/([^/]+)$#', $candidate, $m) === 1) {
            $file = WorkspaceFile::forWorkspace()->find($m[1]);

            return $file instanceof WorkspaceFile ? '/files'.$file->getVirtualPath() : $candidate;
        }
        if (preg_match('#^/tables/by-id/([^/]+)/(rows/by-id/[^/]+\.json|schema\.json)$#', $candidate, $m) === 1) {
            $table = $this->vfsUuidOrNull($m[1]) !== null ? DataTable::forWorkspace()->find($m[1]) : null;

            return $table instanceof DataTable ? '/tables/'.$this->tableToken($table).'/'.$m[2] : $candidate;
        }

        return $candidate;
    }

    /**
     * Candidates from registered mounts (VfsMount::searchCandidates) that
     * overlap a requested path; built-in mounts return none because
     * VfsTextSearchService indexes them. Alias mounts are skipped for `/`.
     * Candidates are only hints: each is stat'ed and read through its mount,
     * so permissions are re-checked like any other target.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function mountSearchCandidates(User $agent, string $pattern, array $paths, VfsBudget $budget, bool $regex): array
    {
        $candidates = [];
        foreach ($this->mounts()->all() as $mount) {
            $prefix = $mount->prefix();
            foreach ($paths as $path) {
                $overlaps = $path === '/'
                    ? ! $mount->isAlias()
                    : ($path === $prefix || str_starts_with($path, $prefix.'/') || str_starts_with($prefix, rtrim($path, '/').'/'));
                if ($overlaps) {
                    array_push($candidates, ...$mount->searchCandidates($agent, $pattern, $path === '/' || str_starts_with($prefix, rtrim($path, '/').'/') ? $prefix : $path, $budget, $regex));
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Backend ref of each requested path, used to narrow candidate queries to
     * a folder, table or channel. Unresolvable paths simply get no scope.
     *
     * @param  list<string>  $paths
     * @return array<string, array{backend_type?: string|null, backend_id?: string|null, type?: string|null}>
     */
    private function searchScopes(User $agent, array $paths): array
    {
        $scopes = [];
        foreach ($paths as $path) {
            if (substr_count($path, '/') < 2) {
                continue;
            }

            try {
                $stat = $this->stat($agent, $path);
            } catch (VfsError) {
                continue;
            }

            $scopes[$path] = [
                'backend_type' => is_string($stat['backend_type'] ?? null) ? $stat['backend_type'] : null,
                'backend_id' => is_string($stat['backend_id'] ?? null) ? $stat['backend_id'] : null,
                'type' => is_string($stat['type'] ?? null) ? $stat['type'] : null,
            ];
        }

        return $scopes;
    }

    private function assertValidSearchRegex(string $expression): void
    {
        if (@preg_match($expression, '') === false) {
            throw VfsError::invalid('Invalid regular expression: '.(preg_last_error_msg() ?: 'compile error').'.', [
                'reason' => 'regex_invalid',
                'hint' => 'Fix the pattern, or search a literal string (grep -F / regex:false).',
            ]);
        }
    }

    /**
     * @return array<int, string> offset => matched text
     */
    private function regexLineMatches(string $expression, string $line, string $pattern): array
    {
        $started = hrtime(true);
        $result = @preg_match_all($expression, $line, $captures, PREG_OFFSET_CAPTURE);
        $this->searchRegexSpentMs += (hrtime(true) - $started) / 1_000_000;

        if ($result === false) {
            $error = preg_last_error();
            $tooExpensive = in_array($error, [PREG_BACKTRACK_LIMIT_ERROR, PREG_RECURSION_LIMIT_ERROR, PREG_JIT_STACKLIMIT_ERROR], true);

            throw VfsError::invalid(
                $tooExpensive ? 'Regular expression is too expensive to evaluate.' : 'Regular expression could not be evaluated: '.preg_last_error_msg().'.',
                [
                    'reason' => $tooExpensive ? 'regex_too_expensive' : 'regex_invalid',
                    'pattern' => Str::limit($pattern, 200),
                    'hint' => 'Simplify the pattern (avoid nested or adjacent unbounded quantifiers such as (a+)+), anchor it, or search a literal string.',
                ],
            );
        }

        if ($this->searchRegexSpentMs > $this->searchRegexTimeBudgetMs) {
            throw VfsError::invalid('Regular expression is too expensive to evaluate within the search time budget.', [
                'reason' => 'regex_too_expensive',
                'pattern' => Str::limit($pattern, 200),
                'budget_ms' => $this->searchRegexTimeBudgetMs,
                'hint' => 'Narrow the search path, simplify the pattern, or search a literal string.',
            ]);
        }

        $matches = [];
        foreach ($captures[0] ?? [] as $capture) {
            $matches[(int) $capture[1]] = (string) $capture[0];
        }

        return $matches;
    }

    /**
     * @return array<int, string>
     */
    private function literalLineMatches(string $pattern, string $line, bool $caseInsensitive): array
    {
        if ($pattern === '') {
            return [];
        }

        $matches = [];
        $offset = 0;
        while (true) {
            $position = $caseInsensitive ? stripos($line, $pattern, $offset) : strpos($line, $pattern, $offset);
            if ($position === false) {
                break;
            }

            $matches[$position] = substr($line, $position, strlen($pattern));
            $offset = $position + max(1, strlen($pattern));
        }

        return $matches;
    }
}
