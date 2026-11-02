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
        $pattern = $this->optionValue($tokens, ['-e']);
        $paths = [];
        $command = $regex ? 'rg' : 'grep';
        $this->assertSupportedOptionsForCommand($tokens, $command);
        $flags = $this->shortFlags($tokens, ['c', 'l', 'n', 'r', 'R', 'i', 'I', 'v', 'w', 'F', 'E', 'o', 'q', 'H', 'h']);
        foreach (['c', 'l', 'n', 'r', 'R', 'i', 'I', 'v', 'w', 'F', 'E', 'o', 'q', 'H', 'h'] as $flag) {
            if (in_array('-'.$flag, $tokens, true)) {
                $flags[$flag] = true;
            }
        }
        $regex = $regex || ($flags['E'] ?? false);
        if ($flags['F'] ?? false) {
            $regex = false;
        }
        $maxCount = $this->integerOptionValue($tokens, ['--max-count', '-m'], $regex ? 'rg' : 'grep', 'max count');
        $maxDepth = $this->integerOptionValue($tokens, ['--max-depth'], $regex ? 'rg' : 'grep', 'max depth');
        $before = $this->integerOptionValue($tokens, ['-B'], $command, 'before context') ?? 0;
        $after = $this->integerOptionValue($tokens, ['-A'], $command, 'after context') ?? 0;
        $context = $this->integerOptionValue($tokens, ['-C'], $command, 'context');
        if ($context !== null) {
            $before = $context;
            $after = $context;
        }
        if ($maxDepth !== null) {
            $budget = new VfsBudget($budget->maxEntries, max(0, $maxDepth), $budget->maxBytes, $budget->maxFiles, $budget->maxMatches, $budget->maxLineLength);
        }
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token, ['--max-count', '-m', '--max-depth', '-A', '-B', '-C', '-e', '--type', '--glob'], true)) {
                $i++;

                continue;
            }
            if ($this->isInlineValueOption($token, ['--max-count=', '--max-depth=', '--type=', '--glob='])) {
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
        $patternExpr = $this->grepPatternExpr($pattern, $regex, $flags);
        if ($regex && @preg_match($patternExpr, '') === false) {
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
            return $this->grepContent($stdin, $pattern, $regex, $flags, $maxCount, $before, $after, null, false, $cwd);
        }

        if ($paths !== [] && ! ($flags['r'] ?? false) && ! ($flags['R'] ?? false) && $this->grepPathsAreReadableFiles($agent, $paths)) {
            $outputs = [];
            $count = 0;
            foreach ($paths as $path) {
                $contentResult = $this->grepContent($this->vfs->read($agent, $path, $budget), $pattern, $regex, $flags, $maxCount, $before, $after, $path, $regex || count($paths) > 1 || ($flags['H'] ?? false), $cwd);
                if (($flags['l'] ?? false) && (int) ($contentResult['count'] ?? 0) > 0) {
                    $outputs[] = $path;
                } elseif (! ($flags['l'] ?? false)) {
                    $outputs[] = (string) $contentResult['stdout'];
                }
                $count += (int) ($contentResult['count'] ?? 0);
            }

            return ['stdout' => implode("\n", array_filter($outputs, fn (string $line): bool => $line !== '')), 'cwd' => $cwd, 'class' => 'read', 'count' => $count];
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
        if ($flags['v'] ?? false) {
            $matches = [];
        }
        if ($flags['q'] ?? false) {
            return ['stdout' => '', 'cwd' => $cwd, 'class' => 'read', 'count' => count($matches)];
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

    private function grepPathsAreReadableFiles(User $agent, array $paths): bool
    {
        foreach ($paths as $path) {
            try {
                $stat = $this->vfs->stat($agent, $path);
            } catch (VfsError) {
                return false;
            }
            if (($stat['type'] ?? null) === 'directory') {
                return false;
            }
        }

        return true;
    }

    private function grepContent(string $content, string $pattern, bool $regex, array $flags, ?int $maxCount, int $before, int $after, ?string $path, bool $prefixPath, string $cwd): array
    {
        $lines = VfsText::lines($content);
        $matches = [];
        $included = [];
        $expr = $this->grepPatternExpr($pattern, $regex, $flags);
        foreach ($lines as $number => $line) {
            $matched = $regex
                ? @preg_match($expr, $line) === 1
                : (($flags['i'] ?? false) ? stripos($line, $pattern) !== false : str_contains($line, $pattern));
            if ($flags['w'] ?? false) {
                $matched = @preg_match($this->grepPatternExpr('\\b'.preg_quote($pattern, '~').'\\b', true, $flags), $line) === 1;
            }
            if ($flags['v'] ?? false) {
                $matched = ! $matched;
            }
            if (! $matched) {
                continue;
            }
            $start = max(0, $number - $before);
            $end = min(count($lines) - 1, $number + $after);
            for ($i = $start; $i <= $end; $i++) {
                $included[$i] = true;
            }
            $matches[] = $number;
            if ($maxCount !== null && count($matches) >= $maxCount) {
                break;
            }
        }
        if ($flags['q'] ?? false) {
            return ['stdout' => '', 'cwd' => $cwd, 'class' => 'read', 'count' => count($matches)];
        }
        if ($flags['c'] ?? false) {
            return ['stdout' => (string) count($matches), 'cwd' => $cwd, 'class' => 'read', 'count' => count($matches)];
        }
        if ($flags['l'] ?? false) {
            return ['stdout' => count($matches) > 0 ? (string) $path : '', 'cwd' => $cwd, 'class' => 'read', 'count' => count($matches)];
        }
        $out = [];
        foreach (array_keys($included) as $index) {
            $line = $lines[$index] ?? '';
            $pieces = [$line];
            if (($flags['o'] ?? false) && ! ($flags['v'] ?? false)) {
                if ($regex) {
                    if (@preg_match_all($expr, $line, $onlyMatches) === false) {
                        throw VfsError::invalid('Invalid regular expression.');
                    }
                    $pieces = $onlyMatches[0] ?? [];
                } else {
                    $pieces = [$pattern];
                }
            }
            foreach ($pieces as $piece) {
                $prefix = '';
                if ($prefixPath && ! ($flags['h'] ?? false) && $path !== null) {
                    $prefix .= $path.':';
                }
                if ($flags['n'] ?? false) {
                    $prefix .= ($index + 1).':';
                }
                $out[] = $prefix.$piece;
            }
        }

        return ['stdout' => implode("\n", $out), 'cwd' => $cwd, 'class' => 'read', 'count' => count($matches)];
    }

    private function grepPatternExpr(string $pattern, bool $regex, array $flags): string
    {
        $body = $regex ? str_replace('~', '\\~', $pattern) : preg_quote($pattern, '~');
        if ($flags['w'] ?? false) {
            $body = '\\b(?:'.$body.')\\b';
        }

        return '~'.$body.'~'.(($flags['i'] ?? false) ? 'i' : '');
    }
}
