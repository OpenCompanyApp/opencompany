<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Tree rendering commands for the VFS shell executor.
 */
trait ExecutesVfsTreeCommands
{
    private function tree(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertSupportedOptions($tokens, 'tree', [], ['-L', '--level'], []);
        $path = $this->firstPathArg($tokens, $cwd) ?? $cwd;
        $maxDepth = $this->integerOptionValue($tokens, ['-L', '--level'], 'tree', 'level');
        if ($maxDepth !== null) {
            $budget = new VfsBudget($budget->maxEntries, max(0, $maxDepth), $budget->maxBytes, $budget->maxFiles, $budget->maxMatches, $budget->maxLineLength);
        }

        $lines = [$path];
        $returned = 0;
        $truncated = false;
        $skipped = 0;
        $this->appendTree($agent, $path, $budget, $lines, 0, '', $returned, $truncated, $skipped);

        return [
            'stdout' => implode("\n", $lines),
            'cwd' => $cwd,
            'class' => 'browse',
            'returned' => $returned,
            'limit' => $budget->maxEntries,
            'max_depth' => $budget->maxDepth,
            'truncated' => $truncated,
            'skipped' => $skipped,
        ];
    }

    private function appendTree(User $agent, string $path, VfsBudget $budget, array &$lines, int $depth, string $prefix, int &$returned, bool &$truncated, int &$skipped): void
    {
        if ($returned >= $budget->maxEntries) {
            $truncated = true;

            return;
        }
        if ($depth >= $budget->maxDepth) {
            try {
                $truncated = $truncated || $this->vfs->list($agent, $path, new VfsBudget(maxEntries: 1)) !== [];
            } catch (VfsError) {
                $skipped++;
            }

            return;
        }

        try {
            $remaining = max(0, $budget->maxEntries - $returned);
            $entries = $this->vfs->list($agent, $path, new VfsBudget(
                maxEntries: min(max(1, $remaining + 1), 1_000),
                maxDepth: $budget->maxDepth,
                maxBytes: $budget->maxBytes,
                maxFiles: $budget->maxFiles,
                maxMatches: $budget->maxMatches,
                maxLineLength: $budget->maxLineLength,
            ));
        } catch (VfsError) {
            $skipped++;

            return;
        }
        if (count($entries) > $remaining) {
            $truncated = true;
            $entries = array_slice($entries, 0, $remaining);
        }

        foreach ($entries as $index => $entry) {
            if ($returned >= $budget->maxEntries) {
                $truncated = true;

                return;
            }
            $last = $index === count($entries) - 1;
            $lines[] = $prefix.($last ? '`-- ' : '|-- ').$entry->name.($entry->type === 'directory' ? '/' : '');
            $returned++;
            if ($entry->type === 'directory') {
                $this->appendTree($agent, $entry->canonicalPath ?? $entry->path, $budget, $lines, $depth + 1, $prefix.($last ? '    ' : '|   '), $returned, $truncated, $skipped);
            }
        }
    }
}
