<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use Carbon\Carbon;

/**
 * Directory navigation and listing: cd and ls.
 *
 * `ls DIR` of a single directory is cursor-pageable: it lists one page of
 * maxEntries through OpenCompanyVfs::listPage, and when more entries exist it
 * ends with the stable truncation line naming the cursor and sets
 * next_cursor. Multi-path and -R listings stay one-shot within maxEntries and
 * say so when cut.
 *
 * Long format never reads content: size and mtime come from entry metadata
 * ("-" when a mount does not report them).
 */
trait ExecutesVfsListCommands
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function cd(User $agent, array $words, string $cwd): array
    {
        if (count($words) > 1) {
            throw VfsError::invalid('cd: too many arguments.');
        }
        $path = $this->path($words[0] ?? '/', $cwd);
        try {
            $this->vfs->listBounded($agent, $path, new VfsBudget(maxEntries: 1));
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_readable') {
                throw VfsError::invalid("cd: {$path}: Not a directory", ['path' => $path]);
            }
            throw $e;
        }

        return ['stdout' => '', 'cwd' => $path, 'class' => 'browse'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function ls(User $agent, array $words, string $cwd, VfsBudget $budget): array
    {
        $args = $this->args('ls', $words);
        $paths = $this->operandPaths($args, $cwd) ?: [$cwd];
        $long = $args->has('l');
        $showHidden = $args->has('a', 'A');
        $blocks = [];
        $fileRows = [];
        $returned = 0;
        $truncated = false;
        $cursorLine = null;
        $pageable = count($paths) === 1 && ! $args->has('R') && ! $args->has('d');

        foreach ($paths as $path) {
            if ($args->has('d')) {
                $fileRows[] = $this->statAsEntry($this->vfs->stat($agent, $path), $path);
                $returned++;

                continue;
            }

            $remaining = $budget->maxEntries - $returned;
            if ($remaining <= 0) {
                $truncated = true;

                break;
            }
            try {
                if ($pageable) {
                    $page = $this->vfs->listPage($agent, $path, $budget->with(['maxEntries' => $remaining]), $this->takeCursor());
                    $entries = $page->entries;
                    if ($page->nextCursor !== null) {
                        $truncated = true;
                        $this->offerNextCursor($page->nextCursor);
                        $cursorLine = VfsText::listingTruncationLine($page->seenBefore + count($entries), 'entries', $page->nextCursor);
                    }
                } else {
                    $entries = $this->vfs->listBounded($agent, $path, $budget->with(['maxEntries' => min(1_000, $remaining + 1)]));
                    if (count($entries) > $remaining) {
                        $truncated = true;
                        $entries = array_slice($entries, 0, $remaining);
                    }
                }
            } catch (VfsError $e) {
                // A file operand: list it like GNU ls does.
                if ($e->errorCode !== 'not_readable') {
                    throw $e;
                }
                $fileRows[] = $this->statAsEntry($this->vfs->stat($agent, $path), $path);
                $returned++;

                continue;
            }
            $returned += count($entries);
            $entries = $this->visibleEntries($entries, $showHidden);
            $this->sortLsEntries($entries, $args->has('t'), $args->has('S'), $args->has('r'));
            if ($args->has('a')) {
                array_unshift($entries, new VfsEntry('.', $path, 'directory'), new VfsEntry('..', dirname($path), 'directory'));
            }
            $blocks[] = ['path' => $path, 'entries' => $entries];

            if ($args->has('R')) {
                $this->appendRecursiveLs($agent, $entries, $budget, $blocks, $args->has('t'), $args->has('S'), $args->has('r'), $showHidden, 1, $returned, $truncated);
            }
        }

        $lines = [];
        if ($fileRows !== []) {
            array_push($lines, ...$this->formatLsEntries($fileRows, $long, $args->has('h'), true));
        }
        $showHeaders = count($paths) > 1 || $args->has('R');
        foreach ($blocks as $index => $block) {
            if ($showHeaders) {
                if ($lines !== []) {
                    $lines[] = '';
                }
                $lines[] = "{$block['path']}:";
            }
            array_push($lines, ...$this->formatLsEntries($block['entries'], $long, $args->has('h'), false));
        }
        if ($truncated) {
            $line = $cursorLine ?? VfsText::listingTruncationLine($returned, 'entries', null);
            if ($this->displayingStdout) {
                $lines[] = $line;
            } else {
                $this->noteTruncation("ls {$line}");
            }
        }

        return [
            'stdout' => $this->linesOut($lines),
            'cwd' => $cwd,
            'class' => 'browse',
            'returned' => $returned,
            'limit' => $budget->maxEntries,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  list<VfsEntry>  $entries
     * @param  list<array{path: string, entries: list<VfsEntry>}>  $blocks
     */
    private function appendRecursiveLs(User $agent, array $entries, VfsBudget $budget, array &$blocks, bool $byTime, bool $bySize, bool $reverse, bool $showHidden, int $depth, int &$returned, bool &$truncated): void
    {
        foreach ($entries as $entry) {
            // Alias mounts (/me) are listed but never descended (VfsEntry::isAlias()).
            if ($entry->type !== 'directory' || $entry->isAlias() || in_array($entry->name, ['.', '..'], true)) {
                continue;
            }
            if ($depth > $budget->maxDepth || $returned >= $budget->maxEntries) {
                $truncated = true;

                return;
            }
            $path = $entry->canonicalPath ?? $entry->path;
            try {
                $remaining = $budget->maxEntries - $returned;
                $children = $this->vfs->listBounded($agent, $path, $budget->with(['maxEntries' => min(1_000, $remaining + 1)]));
            } catch (VfsError) {
                continue;
            }
            if (count($children) > $remaining) {
                $truncated = true;
                $children = array_slice($children, 0, $remaining);
            }
            $returned += count($children);
            $children = $this->visibleEntries($children, $showHidden);
            $this->sortLsEntries($children, $byTime, $bySize, $reverse);
            $blocks[] = ['path' => $path, 'entries' => $children];
            $this->appendRecursiveLs($agent, $children, $budget, $blocks, $byTime, $bySize, $reverse, $showHidden, $depth + 1, $returned, $truncated);
        }
    }

    /**
     * @param  list<VfsEntry>  $entries
     * @return list<VfsEntry>
     */
    private function visibleEntries(array $entries, bool $showHidden): array
    {
        return $showHidden ? $entries : array_values(array_filter($entries, fn (VfsEntry $entry): bool => ! str_starts_with($entry->name, '.')));
    }

    /**
     * GNU order: name by default, -t newest first, -S largest first, -r
     * reverses. Applies within the listed page.
     *
     * @param  list<VfsEntry>  $entries
     */
    private function sortLsEntries(array &$entries, bool $byTime, bool $bySize, bool $reverse): void
    {
        usort($entries, function (VfsEntry $a, VfsEntry $b) use ($byTime, $bySize): int {
            if ($bySize) {
                $cmp = $this->entrySize($b) <=> $this->entrySize($a);
            } elseif ($byTime) {
                $cmp = $this->entryTimestamp($b) <=> $this->entryTimestamp($a);
            } else {
                $cmp = 0;
            }

            return $cmp !== 0 ? $cmp : strcmp(strtolower($a->name), strtolower($b->name));
        });
        if ($reverse) {
            $entries = array_reverse($entries);
        }
    }

    private function entrySize(VfsEntry $entry): int
    {
        return (int) ($entry->metadata['size'] ?? 0);
    }

    private function entryTimestamp(VfsEntry $entry): int
    {
        $updated = $entry->metadata['updated_at'] ?? null;
        if (! is_string($updated) || $updated === '') {
            return 0;
        }
        try {
            return Carbon::parse($updated)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  list<VfsEntry>  $entries
     * @return list<string>
     */
    private function formatLsEntries(array $entries, bool $long, bool $human, bool $operandNames): array
    {
        if (! $long) {
            return array_map(fn (VfsEntry $entry): string => ($operandNames ? $entry->path : $entry->name).($entry->type === 'directory' && ! str_ends_with($entry->name, '/') ? '/' : ''), $entries);
        }

        $rows = array_map(function (VfsEntry $entry) use ($human, $operandNames): array {
            $size = isset($entry->metadata['size']) ? (int) $entry->metadata['size'] : null;
            $updated = $this->entryTimestamp($entry);

            return [
                $this->lsMode($entry),
                $size === null ? '-' : ($human ? $this->humanSize($size) : (string) $size),
                $updated === 0 ? '-' : date('Y-m-d H:i', $updated),
                ($operandNames ? $entry->path : $entry->name).($entry->type === 'directory' ? '/' : ''),
            ];
        }, $entries);
        $sizeWidth = max(array_map(fn (array $row): int => strlen($row[1]), $rows) ?: [1]);
        $timeWidth = max(array_map(fn (array $row): int => strlen($row[2]), $rows) ?: [1]);

        return array_map(function (array $row, VfsEntry $entry) use ($sizeWidth, $timeWidth): string {
            $line = $row[0].' '.str_pad($row[1], $sizeWidth, ' ', STR_PAD_LEFT).' '.str_pad($row[2], $timeWidth).' '.$row[3];
            // Entries that carry a one-line description (root mounts, /me)
            // show it after the name so `ls -l /` doubles as a mount map
            // (Stream C1's addition to the old formatter, kept here).
            $description = $entry->metadata['description'] ?? null;

            return is_string($description) && $description !== '' ? "{$line}  # {$description}" : $line;
        }, $rows, $entries);
    }

    /**
     * Ten-character unix mode string built from VFS capabilities, so `ls -l`
     * output is well-formed for anything that parses it: the type (`d` or
     * `-`), then the calling agent's own r (readable), w (writable,
     * patchable or deletable through some VFS tool) and x (a readable
     * directory can be entered), then `------`: the VFS has no unix
     * group/other permissions, so those triplets are always empty.
     * Examples: `dr-x------`, `drwx------`, `-r--------`, `-rw-------`.
     */
    private function lsMode(VfsEntry $entry): string
    {
        $caps = $entry->capabilities;
        $read = $caps === [] || array_intersect($caps, ['read', 'browse', 'search']) !== [];
        $write = array_intersect($caps, ['write', 'patch', 'delete', 'create', 'append']) !== [];
        $directory = $entry->type === 'directory';

        return ($directory ? 'd' : '-')
            .($read ? 'r' : '-')
            .($write ? 'w' : '-')
            .($directory && $read ? 'x' : '-')
            .'------';
    }

    private function humanSize(int $bytes): string
    {
        $value = (float) $bytes;
        foreach (['', 'K', 'M', 'G', 'T'] as $unit) {
            if ($value < 1024 || $unit === 'T') {
                return $unit === '' ? (string) (int) $value : (($value < 10 ? (string) round($value, 1) : (string) round($value))).$unit;
            }
            $value /= 1024;
        }

        return (string) $bytes;
    }

    /**
     * @param  array<string, mixed>  $stat
     */
    private function statAsEntry(array $stat, string $path): VfsEntry
    {
        return new VfsEntry(
            name: (string) ($stat['name'] ?? basename($path)),
            path: $path,
            type: (string) ($stat['type'] ?? 'file'),
            canonicalPath: isset($stat['canonical_path']) ? (string) $stat['canonical_path'] : null,
            version: isset($stat['version']) ? (string) $stat['version'] : null,
            capabilities: array_values(array_map('strval', (array) ($stat['capabilities'] ?? []))),
            metadata: is_array($stat['metadata'] ?? null) ? $stat['metadata'] : [],
        );
    }
}
