<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPageCursor;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use Carbon\Carbon;

/**
 * `find` over bounded virtual trees.
 *
 * Expression grammar (GNU precedence): `( expr )`, `! expr` / `-not expr`,
 * implicit or explicit `-a`/`-and`, `-o`/`-or`. Tests: -name -iname -path
 * -ipath -type -empty -size -mtime -mmin; actions -print -print0 -ls; global
 * options -maxdepth -mindepth anywhere.
 *
 * Cost invariant: candidates are lazy. Name/path/type tests never read
 * content; size, -empty and mtime use entry metadata first and only stat or
 * read when a predicate actually asks and metadata has no answer.
 *
 * Budgets: maxEntries results, maxFiles visited entries, maxDepth levels.
 * When a budget stops the walk, the output ends with the stable truncation
 * line and next_cursor resumes after the last visited entry (the walk is
 * re-run and the already-visited prefix skipped without counting against
 * the budget; listings are ordered deterministically by the mounts).
 */
trait ExecutesVfsFindCommands
{
    private const FIND_CURSOR_ADAPTER = 'vfs-shell-find-v1';

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function find(User $agent, array $words, string $cwd, VfsBudget $budget): array
    {
        // Leading operands up to the first expression token are start paths.
        $roots = [];
        $index = 0;
        while ($index < count($words) && ! $this->isFindExpressionStart($words[$index], $this->argLiteral[$index] ?? false)) {
            array_push($roots, ...$this->expandPathArg($words[$index], $cwd, $this->argGlob[$index] ?? false));
            $index++;
        }
        $roots = $roots ?: [$cwd];
        $expression = array_slice($words, $index);

        $maxDepth = null;
        $minDepth = 0;
        $filtered = [];
        for ($i = 0; $i < count($expression); $i++) {
            $token = $expression[$i];
            if (in_array($token, ['-maxdepth', '-maxDepth', '--max-depth', '-mindepth'], true)) {
                $value = $expression[$i + 1] ?? null;
                if ($value === null || preg_match('/^\d+$/', $value) !== 1) {
                    throw VfsError::invalid("find {$token} requires a non-negative integer.");
                }
                $token === '-mindepth' ? $minDepth = (int) $value : $maxDepth = (int) $value;
                $i++;

                continue;
            }
            if (str_starts_with($token, '--max-depth=')) {
                $maxDepth = (int) substr($token, strlen('--max-depth='));

                continue;
            }
            $filtered[] = $token;
        }
        if (in_array('-exec', $filtered, true) || in_array('-execdir', $filtered, true) || in_array('-delete', $filtered, true) || in_array('-ok', $filtered, true)) {
            throw VfsError::unsupported('find -exec/-delete are not supported in VFS find.', [
                'suggestion' => 'Pipe into xargs instead: find /files/tmp -name "*.log" -print0 | xargs -0 rm',
            ]);
        }
        if ($maxDepth !== null) {
            $budget = $budget->with(['maxDepth' => $maxDepth]);
        }
        $position = 0;
        $matcher = $filtered === [] ? fn (array $c): bool => true : $this->parseFindOr($filtered, $position);
        if ($position !== count($filtered)) {
            throw VfsError::invalid('find: unexpected expression token "'.$filtered[$position].'".', ['hint' => 'Group with \\( ... \\) and combine tests with -a, -o, !.']);
        }
        $print0 = in_array('-print0', $filtered, true);
        $lsFormat = in_array('-ls', $filtered, true);

        $scope = VfsPageCursor::agentScope($agent);
        $cursorKey = implode('|', $roots).'#'.hash('sha256', implode("\0", $words));
        $cursor = $this->takeCursor();
        $skip = $cursor === null ? 0 : (int) (VfsPageCursor::decode($cursor, $cursorKey, self::FIND_CURSOR_ADAPTER, $scope)['state']['visited'] ?? 0);

        $state = ['lines' => [], 'visited' => 0, 'scanned' => 0, 'skipped' => 0, 'stop' => null, 'skip' => $skip, 'identities' => []];
        foreach ($roots as $root) {
            $this->walkFind($agent, $root, $budget, $matcher, $minDepth, $lsFormat, $state);
            if ($state['stop'] !== null) {
                break;
            }
        }

        $lines = $state['lines'];
        $truncated = $state['stop'] !== null;
        $line = '';
        $nextCursor = null;
        if ($truncated) {
            $nextCursor = VfsPageCursor::encode($cursorKey, self::FIND_CURSOR_ADAPTER, $scope, ['visited' => $state['visited']], $skip + count($lines));
            $this->offerNextCursor($nextCursor);
            $line = $state['stop'] === 'entries'
                ? VfsText::listingTruncationLine(count($lines), 'matches', $nextCursor)
                : "[truncated: visited {$state['scanned']} entries (maxFiles) — pass cursor \"{$nextCursor}\" or raise maxFiles / narrow the path]";
            if (! $this->displayingStdout) {
                $this->noteTruncation('find '.$line);
            }
        }
        $stdout = $print0 ? implode("\0", $lines).($lines === [] ? '' : "\0") : $this->linesOut($lines);
        if ($truncated && $this->displayingStdout) {
            $stdout .= ($print0 && $stdout !== '' ? "\n" : '').$line."\n";
        }

        return [
            'stdout' => $stdout,
            'cwd' => $cwd,
            'class' => 'browse',
            'returned' => count($lines),
            'limit' => $budget->maxEntries,
            'scanned' => $state['scanned'],
            'max_files' => $budget->maxFiles,
            'skipped' => $state['skipped'],
            'truncated' => $truncated,
        ];
    }

    private function isFindExpressionStart(string $word, bool $literal): bool
    {
        // Like GNU find, operators are recognized by text alone: the shell
        // removed the quoting of \( and "!" before find sees them.
        return (str_starts_with($word, '-') && $word !== '-') || in_array($word, ['(', ')', '!', ','], true);
    }

    /**
     * Depth-first pre-order walk (GNU order).
     *
     * @param  array{lines: list<string>, visited: int, scanned: int, skipped: int, stop: string|null, skip: int, identities: array<string, true>}  $state
     */
    private function walkFind(User $agent, string $root, VfsBudget $budget, callable $matcher, int $minDepth, bool $lsFormat, array &$state): void
    {
        try {
            $stat = $this->vfs->stat($agent, $root);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                throw $e;
            }
            $state['skipped']++;

            return;
        }
        $rootCandidate = $this->findCandidate($agent, $root === '/' ? '/' : basename($root), $root, (string) ($stat['type'] ?? 'directory'), $stat, 0);
        $this->visitFind($agent, $rootCandidate, $budget, $matcher, $minDepth, $lsFormat, $state);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array{lines: list<string>, visited: int, scanned: int, skipped: int, stop: string|null, skip: int, identities: array<string, true>}  $state
     */
    private function visitFind(User $agent, array $candidate, VfsBudget $budget, callable $matcher, int $minDepth, bool $lsFormat, array &$state): void
    {
        if ($state['stop'] !== null) {
            return;
        }
        $state['visited']++;
        $resuming = $state['visited'] <= $state['skip'];
        if (! $resuming) {
            if ($state['scanned'] >= $budget->maxFiles) {
                $state['stop'] = 'files';
                $state['visited']--;

                return;
            }
            $state['scanned']++;
            if ($candidate['depth'] >= $minDepth && $matcher($candidate)) {
                if (count($state['lines']) >= $budget->maxEntries) {
                    $state['stop'] = 'entries';
                    $state['visited']--;
                    $state['scanned']--;

                    return;
                }
                $state['lines'][] = $lsFormat ? $this->formatFindLs($agent, $candidate) : (string) $candidate['path'];
            }
        }

        if ($candidate['type'] !== 'directory' || $candidate['depth'] >= $budget->maxDepth) {
            return;
        }
        try {
            $entries = $this->vfs->listBounded($agent, $candidate['path'], $budget->with(['maxEntries' => 1_000]));
        } catch (VfsError) {
            $state['skipped']++;

            return;
        }
        $prefix = rtrim($candidate['path'], '/').'/';
        foreach ($entries as $entry) {
            // Display paths under the start path (so -path and output read
            // naturally); each object is visited once even when a mount
            // exposes it twice (slug and by-id).
            $path = str_starts_with($entry->path, $prefix) ? $entry->path : $prefix.$entry->name;
            $identity = $entry->canonicalPath ?? $path;
            if (isset($state['identities'][$identity])) {
                continue;
            }
            $state['identities'][$identity] = true;
            $child = $this->findCandidate($agent, $entry->name, $path, $entry->type, $entry->jsonSerialize(), $candidate['depth'] + 1);
            $this->visitFind($agent, $child, $budget, $matcher, $minDepth, $lsFormat, $state);
            if ($state['stop'] !== null) {
                return;
            }
        }
    }

    /**
     * Lazy candidate: size/empty/mtime are closures evaluated on demand.
     *
     * @param  array<string, mixed>  $stat
     * @return array<string, mixed>
     */
    private function findCandidate(User $agent, string $name, string $path, string $type, array $stat, int $depth): array
    {
        $metadata = is_array($stat['metadata'] ?? null) ? $stat['metadata'] : [];
        $cache = [];
        $fullStat = function () use ($agent, $path, &$cache): array {
            return $cache['stat'] ??= (function () use ($agent, $path): array {
                try {
                    return $this->vfs->stat($agent, $path);
                } catch (VfsError) {
                    return [];
                }
            })();
        };

        $size = function () use ($agent, $path, $type, $stat, $metadata, $fullStat, &$cache): int {
            if (isset($cache['size'])) {
                return $cache['size'];
            }
            $size = $stat['size'] ?? $metadata['size'] ?? null;
            if ($size === null && $type === 'file') {
                $full = $fullStat();
                $size = $full['size'] ?? $full['metadata']['size'] ?? null;
                if ($size === null) {
                    // Last resort, only when a size predicate asks.
                    try {
                        $size = strlen($this->readFull($agent, $path));
                    } catch (VfsError) {
                        $size = 0;
                    }
                }
            }

            return $cache['size'] = (int) ($size ?? 0);
        };

        return [
            'name' => $name,
            'path' => $path,
            'type' => $type,
            'depth' => $depth,
            'markdown' => ($metadata['content_format'] ?? null) === 'markdown',
            'size' => $size,
            'empty' => function () use ($agent, $path, $type, $size): bool {
                if ($type !== 'directory') {
                    return $size() === 0;
                }
                try {
                    return $this->vfs->listBounded($agent, $path, new VfsBudget(maxEntries: 1)) === [];
                } catch (VfsError) {
                    return false;
                }
            },
            'mtime' => function () use ($stat, $metadata, $fullStat): ?int {
                $updated = $stat['updated_at'] ?? $metadata['updated_at'] ?? null;
                if (! is_string($updated) || $updated === '') {
                    $full = $fullStat();
                    $updated = $full['updated_at'] ?? $full['metadata']['updated_at'] ?? null;
                }
                if (! is_string($updated) || $updated === '') {
                    return null;
                }
                try {
                    return Carbon::parse($updated)->getTimestamp();
                } catch (\Throwable) {
                    return null;
                }
            },
            'version' => $stat['version'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $tokens
     */
    private function parseFindOr(array $tokens, int &$i): callable
    {
        $left = $this->parseFindAnd($tokens, $i);
        while (in_array($tokens[$i] ?? null, ['-o', '-or'], true)) {
            $i++;
            $right = $this->parseFindAnd($tokens, $i);
            $prev = $left;
            $left = fn (array $c): bool => $prev($c) || $right($c);
        }

        return $left;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function parseFindAnd(array $tokens, int &$i): callable
    {
        $left = $this->parseFindNot($tokens, $i);
        while (($tokens[$i] ?? null) !== null && ! in_array($tokens[$i], ['-o', '-or', ')'], true)) {
            if (in_array($tokens[$i], ['-a', '-and'], true)) {
                $i++;
            }
            $right = $this->parseFindNot($tokens, $i);
            $prev = $left;
            $left = fn (array $c): bool => $prev($c) && $right($c);
        }

        return $left;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function parseFindNot(array $tokens, int &$i): callable
    {
        if (in_array($tokens[$i] ?? null, ['!', '-not'], true)) {
            $i++;
            $inner = $this->parseFindNot($tokens, $i);

            return fn (array $c): bool => ! $inner($c);
        }
        if (($tokens[$i] ?? null) === '(') {
            $i++;
            $inner = $this->parseFindOr($tokens, $i);
            if (($tokens[$i] ?? null) !== ')') {
                throw VfsError::invalid('find: missing closing ).', ['hint' => 'Escape or quote parentheses: find /docs \\( -name a -o -name b \\)']);
            }
            $i++;

            return $inner;
        }

        return $this->parseFindPrimary($tokens, $i);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function parseFindPrimary(array $tokens, int &$i): callable
    {
        $token = $tokens[$i] ?? throw VfsError::invalid('find: expected a test after an operator.');
        $i++;
        $value = function () use ($tokens, &$i, $token): string {
            if (! array_key_exists($i, $tokens)) {
                throw VfsError::invalid("find {$token} requires a value.");
            }

            return $tokens[$i++];
        };

        return match ($token) {
            '-name', '-iname' => $this->findNamePredicate($value(), $token === '-iname'),
            '-path', '-wholename', '-ipath' => $this->findPathPredicate($value(), $token === '-ipath'),
            '-type' => $this->findTypePredicate($value()),
            '-empty' => fn (array $c): bool => ($c['empty'])(),
            '-size' => $this->findSizePredicate($value()),
            '-mtime' => $this->findAgePredicate($value(), 86_400, '-mtime'),
            '-mmin' => $this->findAgePredicate($value(), 60, '-mmin'),
            '-print', '-print0', '-ls', '-true' => fn (array $c): bool => true,
            '-false' => fn (array $c): bool => false,
            '-prune', '-regex', '-iregex', '-newer', '-user', '-group', '-perm', '-links', '-inum', '-printf', '-fprint' => throw VfsError::unsupported("find {$token} is not supported in VFS find.", [
                'supported' => ['-name', '-iname', '-path', '-ipath', '-type', '-empty', '-size', '-mtime', '-mmin', '-maxdepth', '-mindepth', '-print', '-print0', '-ls', '!', '-not', '-a', '-o', '( )'],
            ]),
            default => throw VfsError::invalid("find: unknown predicate {$token}.", [
                'supported' => ['-name', '-iname', '-path', '-ipath', '-type', '-empty', '-size', '-mtime', '-mmin', '-maxdepth', '-mindepth', '-print', '-print0', '-ls'],
            ]),
        };
    }

    /**
     * Docs are markdown records whose names may lack ".md"; a pattern ending
     * in .md also matches them (ergonomic alias, same for -iname).
     */
    private function findNamePredicate(string $pattern, bool $ignoreCase): callable
    {
        if ($pattern === '') {
            throw VfsError::invalid('find -name requires a pattern.');
        }
        $flags = $ignoreCase ? FNM_CASEFOLD : 0;

        return fn (array $c): bool => fnmatch($pattern, $c['name'], $flags)
            || ($c['markdown'] && $c['type'] === 'file' && ! str_ends_with(strtolower($c['name']), '.md') && fnmatch($pattern, $c['name'].'.md', $flags));
    }

    private function findPathPredicate(string $pattern, bool $ignoreCase): callable
    {
        $flags = $ignoreCase ? FNM_CASEFOLD : 0;

        return fn (array $c): bool => fnmatch($pattern, $c['path'], $flags);
    }

    private function findTypePredicate(string $type): callable
    {
        $kind = match ($type) {
            'f', 'file' => 'file',
            'd', 'directory' => 'directory',
            default => throw VfsError::invalid('find -type supports f (file) and d (directory); the VFS has no links, sockets or devices.'),
        };

        return fn (array $c): bool => $c['type'] === $kind;
    }

    /**
     * GNU -size: N[cwbkMG], default unit 512-byte blocks, sizes rounded up to
     * whole units; +N greater, -N less, N exactly.
     */
    private function findSizePredicate(string $expression): callable
    {
        if (preg_match('/^([+-]?)(\d+)([cwbkMG]?)$/', $expression, $m) !== 1) {
            throw VfsError::invalid("find -size {$expression}: expected [+-]N[cwbkMG], e.g. -size +10k.");
        }
        $unit = match ($m[3]) {
            'c' => 1,
            'w' => 2,
            'k' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 512,
        };
        $n = (int) $m[2];
        $sign = $m[1];

        return function (array $c) use ($unit, $n, $sign): bool {
            if ($c['type'] !== 'file') {
                return false;
            }
            $units = (int) ceil(($c['size'])() / $unit);

            return match ($sign) {
                '+' => $units > $n,
                '-' => $units < $n,
                default => $units === $n,
            };
        };
    }

    /**
     * -mtime N (days) / -mmin N (minutes): age = floor((now - updated_at) / unit).
     * Computed from timestamps directly; Carbon 3's signed diffInDays made
     * every age 0 before.
     */
    private function findAgePredicate(string $expression, int $unitSeconds, string $option): callable
    {
        if (preg_match('/^([+-]?)(\d+)$/', $expression, $m) !== 1) {
            throw VfsError::invalid("find {$option} {$expression}: expected [+-]N.");
        }
        $n = (int) $m[2];
        $sign = $m[1];
        $now = now()->getTimestamp();

        return function (array $c) use ($n, $sign, $now, $unitSeconds): bool {
            $mtime = ($c['mtime'])();
            if ($mtime === null) {
                return false;
            }
            $age = (int) floor(max(0, $now - $mtime) / $unitSeconds);

            return match ($sign) {
                '+' => $age > $n,
                '-' => $age < $n,
                default => $age === $n,
            };
        };
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function formatFindLs(User $agent, array $candidate): string
    {
        $mtime = ($candidate['mtime'])();

        return sprintf('%s %s %s %s', $candidate['type'] === 'directory' ? 'd' : '-', $candidate['type'] === 'file' ? (string) ($candidate['size'])() : '-', $mtime === null ? '-' : date('Y-m-d H:i', $mtime), $candidate['path']);
    }
}
