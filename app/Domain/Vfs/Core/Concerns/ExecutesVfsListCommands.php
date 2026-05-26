<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Directory navigation and listing commands for the VFS shell executor.
 */
trait ExecutesVfsListCommands
{
    private function cd(User $agent, array $tokens, string $cwd): array
    {
        $path = $this->path($tokens[0] ?? '/', $cwd);
        $this->vfs->list($agent, $path, new VfsBudget(maxEntries: 1));

        return ['stdout' => '', 'cwd' => $path, 'class' => 'browse'];
    }

    private function ls(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        [$flags, $paths] = $this->flagsAndPathArgs($tokens, $cwd, ['1', 'a', 'l', 'R']);
        $paths = $paths === [] ? [$cwd] : $paths;
        $lines = [];
        $returned = 0;
        $truncated = false;

        foreach ($paths as $path) {
            if ($returned >= $budget->maxEntries) {
                $truncated = true;

                break;
            }

            try {
                $remaining = max(0, $budget->maxEntries - $returned);
                $entries = $this->vfs->list($agent, $path, $this->pageProbeBudget($budget, $remaining));
            } catch (VfsError $e) {
                if ($e->errorCode !== 'not_readable') {
                    throw $e;
                }

                $stat = $this->vfs->stat($agent, $path);
                $lines[] = $this->formatLsStat($stat, $flags['l'] ?? false);
                $returned++;

                continue;
            }
            if (count($entries) > $remaining) {
                $truncated = true;
                $entries = array_slice($entries, 0, $remaining);
            }
            $returned += count($entries);
            if (count($paths) > 1) {
                $lines[] = "{$path}:";
            }
            foreach ($entries as $entry) {
                $lines[] = $this->formatLsEntry($entry, $flags['l'] ?? false);
            }

            if (($flags['R'] ?? false) === true) {
                foreach ($entries as $entry) {
                    if ($entry->type !== 'directory') {
                        continue;
                    }

                    $this->appendRecursiveLs(
                        $agent,
                        $entry->canonicalPath ?? $entry->path,
                        $budget,
                        $lines,
                        $flags,
                        1,
                        $returned,
                        $truncated,
                    );
                }
            }
        }

        return [
            'stdout' => implode("\n", $lines),
            'cwd' => $cwd,
            'class' => 'browse',
            'returned' => $returned,
            'limit' => $budget->maxEntries,
            'truncated' => $truncated,
        ];
    }

    private function appendRecursiveLs(User $agent, string $path, VfsBudget $budget, array &$lines, array $flags, int $depth, int &$returned, bool &$truncated): void
    {
        if ($depth > $budget->maxDepth || $returned >= $budget->maxEntries) {
            $truncated = true;

            return;
        }

        try {
            $remaining = max(0, $budget->maxEntries - $returned);
            $entries = $this->vfs->list($agent, $path, $this->pageProbeBudget($budget, $remaining));
        } catch (VfsError) {
            return;
        }

        if (count($entries) > $remaining) {
            $truncated = true;
            $entries = array_slice($entries, 0, $remaining);
        }
        $returned += count($entries);
        $lines[] = '';
        $lines[] = "{$path}:";
        foreach ($entries as $entry) {
            $lines[] = $this->formatLsEntry($entry, $flags['l'] ?? false);
        }

        foreach ($entries as $entry) {
            if ($entry->type === 'directory') {
                $this->appendRecursiveLs($agent, $entry->canonicalPath ?? $entry->path, $budget, $lines, $flags, $depth + 1, $returned, $truncated);
            }
        }
    }

    private function formatLsEntry(VfsEntry $entry, bool $long): string
    {
        $name = $entry->type === 'directory' ? $entry->name.'/' : $entry->name;
        if (! $long) {
            return $name;
        }

        $type = $entry->type === 'directory' ? 'd' : '-';
        $capabilities = implode(',', $entry->capabilities);
        $version = $entry->version ?? '-';

        return sprintf('%s %-24s %-36s %s', $type, $capabilities, $version, $name);
    }

    /**
     * Format a direct stat result for `ls file`, matching common unix muscle memory.
     *
     * @param  array<string, mixed>  $stat
     */
    private function formatLsStat(array $stat, bool $long): string
    {
        $name = (string) ($stat['name'] ?? basename((string) ($stat['path'] ?? '')));
        if (($stat['type'] ?? null) === 'directory') {
            $name .= '/';
        }
        if (! $long) {
            return $name;
        }

        $type = ($stat['type'] ?? null) === 'directory' ? 'd' : '-';
        $capabilities = implode(',', array_map('strval', (array) ($stat['capabilities'] ?? [])));
        $version = (string) ($stat['version'] ?? '-');

        return sprintf('%s %-24s %-36s %s', $type, $capabilities, $version, $name);
    }

    private function pageProbeBudget(VfsBudget $budget, int $remaining): VfsBudget
    {
        return new VfsBudget(
            maxEntries: min(max(1, $remaining + 1), 1_000),
            maxDepth: $budget->maxDepth,
            maxBytes: $budget->maxBytes,
            maxFiles: $budget->maxFiles,
            maxMatches: $budget->maxMatches,
            maxLineLength: $budget->maxLineLength,
        );
    }
}
