<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Public text-search operation for VFS paths.
 *
 * Candidate selection can use indexed source tables, but every returned match
 * is verified by reading the VFS path through normal permission checks.
 */
trait SearchesVfsContent
{
    /**
     * @return list<array{path: string, line?: int, snippet: string, match?: string, backend: string}>
     */
    public function search(User $agent, string $pattern, array $paths, ?VfsBudget $budget = null, bool $regex = false, bool $caseInsensitive = false): array
    {
        return $this->searchWithMetadata($agent, $pattern, $paths, $budget, $regex, $caseInsensitive)['matches'];
    }

    /**
     * @return array{matches: list<array{path: string, line?: int, snippet: string, match?: string, backend: string}>, scanned: int, max_files: int, skipped: int, truncated: bool, backend: string}
     */
    public function searchWithMetadata(User $agent, string $pattern, array $paths, ?VfsBudget $budget = null, bool $regex = false, bool $caseInsensitive = false): array
    {
        $budget ??= new VfsBudget;
        $paths = $paths === [] ? ['/'] : $paths;
        $matches = [];
        $scanned = 0;
        $skipped = 0;
        $seenFiles = [];
        $seenMatches = [];
        $truncated = false;
        $normalizedPaths = array_map(fn (string $path): string => $this->normalizePath($path), $paths);
        if ($regex) {
            $this->assertValidSearchRegex($pattern);
        }
        $candidatePaths = $this->textSearch->candidatePaths($agent, $pattern, $normalizedPaths, $budget, $regex);
        $searchPaths = $candidatePaths !== [] ? array_values(array_unique([...$normalizedPaths, ...$candidatePaths])) : $normalizedPaths;
        $backend = $candidatePaths !== [] ? 'source_index+live' : 'live';

        foreach ($searchPaths as $path) {
            foreach ($this->walkReadableFiles($agent, $this->normalizePath((string) $path), $budget) as $filePath) {
                if ($scanned >= $budget->maxFiles) {
                    $truncated = true;

                    break 2;
                }

                try {
                    $stat = $this->stat($agent, $filePath);
                } catch (VfsError) {
                    $skipped++;

                    continue;
                }

                $fileKey = (string) ($stat['canonical_path'] ?? $filePath);
                if (isset($seenFiles[$fileKey])) {
                    continue;
                }
                $seenFiles[$fileKey] = true;

                if (count($matches) >= $budget->maxMatches) {
                    break 2;
                }

                try {
                    $content = $this->read($agent, $filePath, $budget);
                } catch (VfsError) {
                    $skipped++;

                    continue;
                }

                $scanned++;
                foreach (VfsText::lines($content) as $lineNumber => $line) {
                    $expression = '~'.str_replace('~', '\~', $pattern).'~'.($caseInsensitive ? 'i' : '');
                    $lineMatches = $regex
                        ? $this->regexLineMatches($expression, $line)
                        : $this->literalLineMatches($pattern, $line, $caseInsensitive);

                    if ($lineMatches === []) {
                        continue;
                    }

                    foreach ($lineMatches as $offset => $matchedText) {
                        $matchKey = $filePath.':'.($lineNumber + 1).':'.$offset.':'.$matchedText;
                        if (isset($seenMatches[$matchKey])) {
                            continue;
                        }
                        $seenMatches[$matchKey] = true;

                        $matches[] = [
                            'path' => $filePath,
                            'line' => $lineNumber + 1,
                            'snippet' => Str::limit($line, $budget->maxLineLength, '...'),
                            'match' => $matchedText,
                            'backend' => $backend,
                        ];

                        if (count($matches) >= $budget->maxMatches) {
                            $truncated = true;

                            break 4;
                        }
                    }
                }
            }
        }

        return [
            'matches' => $matches,
            'scanned' => $scanned,
            'max_files' => $budget->maxFiles,
            'skipped' => $skipped,
            'truncated' => $truncated || count($matches) >= $budget->maxMatches,
            'backend' => $backend,
        ];
    }

    private function assertValidSearchRegex(string $pattern): void
    {
        $expression = '~'.str_replace('~', '\~', $pattern).'~';
        if (@preg_match($expression, '') === false) {
            throw VfsError::invalid('Invalid regular expression.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function regexLineMatches(string $expression, string $line): array
    {
        $matches = [];
        $result = @preg_match_all($expression, $line, $captures, PREG_OFFSET_CAPTURE);
        if ($result === false || $result < 1) {
            return [];
        }

        foreach ($captures[0] ?? [] as $capture) {
            $matches[(int) ($capture[1] ?? count($matches))] = (string) ($capture[0] ?? '');
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
