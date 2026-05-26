<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * grep/search/rg command support.
 *
 * Search behavior lives outside basic read commands because it has distinct
 * regex, recursion, match-count, and backend-selection semantics.
 */
trait ExecutesVfsSearchCommands
{
    private function grep(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin, bool $regex): array
    {
        $pattern = null;
        $paths = [];
        $this->assertSupportedOptions($tokens, $regex ? 'rg' : 'grep', ['c', 'l', 'n', 'r', 'R', 'i', 'o'], ['--max-count', '-m', '--max-depth'], []);
        $flags = $this->shortFlags($tokens, ['c', 'l', 'n', 'r', 'R', 'i', 'o']);
        $maxCount = $this->integerOptionValue($tokens, ['--max-count', '-m'], $regex ? 'rg' : 'grep', 'max count');
        $maxDepth = $this->integerOptionValue($tokens, ['--max-depth'], $regex ? 'rg' : 'grep', 'max depth');
        if ($maxDepth !== null) {
            $budget = new VfsBudget($budget->maxEntries, max(0, $maxDepth), $budget->maxBytes, $budget->maxFiles, $budget->maxMatches, $budget->maxLineLength);
        }
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token, ['--max-count', '-m', '--max-depth'], true)) {
                $i++;

                continue;
            }
            if ($this->isInlineValueOption($token, ['--max-count=', '--max-depth='])) {
                continue;
            }
            if (str_starts_with($token, '-')) {
                continue;
            }
            if ($pattern === null) {
                $pattern = $token;
            } else {
                array_push($paths, ...$this->expandPathArg($token, $cwd, ! ($this->lastTokenQuoteMask[$i] ?? false)));
            }
        }

        if ($pattern === null) {
            throw VfsError::invalid('grep/rg requires a pattern.');
        }
        if ($regex && @preg_match('~'.str_replace('~', '\\~', $pattern).'~', '') === false) {
            throw VfsError::invalid('Invalid regular expression.');
        }
        if ($maxCount === 0 && $stdin !== null && $paths === []) {
            return [
                'stdout' => ($flags['c'] ?? false) ? '0' : '',
                'cwd' => $cwd,
                'class' => 'read',
                'count' => 0,
            ];
        }

        if ($stdin !== null && $paths === []) {
            $matches = [];
            foreach (VfsText::lines($stdin) as $number => $line) {
                $patternExpr = '~'.str_replace('~', '\\~', $pattern).'~'.(($flags['i'] ?? false) ? 'i' : '');
                $matched = $regex
                    ? @preg_match($patternExpr, $line) === 1
                    : (($flags['i'] ?? false) ? stripos($line, $pattern) !== false : str_contains($line, $pattern));

                if ($matched) {
                    $outputs = [$line];
                    if ($flags['o'] ?? false) {
                        if ($regex && @preg_match_all($patternExpr, $line, $onlyMatches) === false) {
                            throw VfsError::invalid('Invalid regular expression.');
                        }
                        $outputs = $regex ? ($onlyMatches[0] ?? []) : [$pattern];
                    }

                    foreach ($outputs as $output) {
                        $matches[] = ($flags['n'] ?? false) ? ($number + 1).':'.$output : $output;
                    }

                    if ($maxCount !== null && count($matches) >= $maxCount) {
                        break;
                    }
                }
            }

            return [
                'stdout' => ($flags['c'] ?? false) ? (string) count($matches) : implode("\n", $matches),
                'cwd' => $cwd,
                'class' => 'read',
                'count' => count($matches),
            ];
        }

        // `-R` is accepted for unix muscle memory, but recursion is already
        // governed by VFS budgets and adapters. It must not change literal
        // grep into regex search; only `rg` opts into regex semantics.
        $searchedPaths = $paths ?: [$cwd];
        $matches = $this->vfs->search($agent, $pattern, $searchedPaths, $budget, $regex, $flags['i'] ?? false);
        if ($maxCount !== null) {
            $perPathLine = [];
            $acceptedLines = [];
            $matches = array_values(array_filter($matches, function (array $match) use (&$perPathLine, &$acceptedLines, $maxCount): bool {
                $path = (string) ($match['path'] ?? '');
                $line = (int) ($match['line'] ?? 0);
                $lineKey = $path.':'.$line;
                if (isset($acceptedLines[$lineKey])) {
                    return true;
                }

                $perPathLine[$path] ??= 0;
                if ($perPathLine[$path] >= $maxCount) {
                    return false;
                }
                $perPathLine[$path]++;
                $acceptedLines[$lineKey] = true;

                return true;
            }));
        }

        if ($flags['l'] ?? false) {
            $lines = array_values(array_unique(array_map(fn (array $match): string => (string) $match['path'], $matches)));
        } elseif ($flags['c'] ?? false) {
            $counts = [];
            foreach ($searchedPaths as $path) {
                $counts[$path] = [];
            }
            foreach ($matches as $match) {
                $path = (string) $match['path'];
                $lineKey = $path.':'.(string) ($match['line'] ?? '');
                $counts[$path] ??= [];
                $counts[$path][$lineKey] = true;
            }
            $lines = count($counts) === 1
                ? [(string) count(array_values($counts)[0])]
                : array_map(fn (string $path, array $pathLines): string => "{$path}:".count($pathLines), array_keys($counts), $counts);
        } elseif ($flags['o'] ?? false) {
            $multiplePaths = count(array_unique(array_map(fn (array $match): string => (string) ($match['path'] ?? ''), $matches))) > 1;
            $lines = array_map(function (array $match) use ($pattern, $flags, $multiplePaths): string {
                $prefix = $multiplePaths ? (string) ($match['path'] ?? '').':' : '';
                $prefix .= ($flags['n'] ?? false) ? (string) ($match['line'] ?? '').':' : '';

                return $prefix.(string) ($match['match'] ?? $pattern);
            }, $matches);
        } else {
            $seenLines = [];
            $lines = [];
            foreach ($matches as $match) {
                $line = ($flags['n'] ?? false)
                    ? "{$match['path']}:{$match['line']}:{$match['snippet']}"
                    : "{$match['path']}:{$match['snippet']}";
                if (isset($seenLines[$line])) {
                    continue;
                }
                $seenLines[$line] = true;
                $lines[] = $line;
            }
        }

        return [
            'stdout' => implode("\n", $lines),
            'cwd' => $cwd,
            'class' => 'read',
            'count' => count($matches),
            'returned' => count($matches),
            'limit' => $budget->maxMatches,
            'truncated' => count($matches) >= $budget->maxMatches,
        ];
    }
}
