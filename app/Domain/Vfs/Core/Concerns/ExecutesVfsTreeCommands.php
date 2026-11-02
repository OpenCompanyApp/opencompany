<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Bounded `tree` rendering.
 *
 * Bounds: maxEntries rendered entries, maxDepth (or -L) levels, and at most
 * MAX_DEPTH_PROBES one-entry probes of directories at the depth limit (to
 * tell "cut by depth" from "empty"); beyond that budget, unprobed
 * directories count as truncated, conservatively.
 */
trait ExecutesVfsTreeCommands
{
    private int $treeDepthProbes = 0;

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function tree(User $agent, array $words, string $cwd, VfsBudget $budget): array
    {
        $args = $this->args('tree', $words);
        $path = $this->operandPaths($args, $cwd)[0] ?? $cwd;
        $level = $args->int(['-L', '--level']);
        if ($level !== null) {
            $budget = $budget->with(['maxDepth' => max(0, $level)]);
        }

        $lines = [$path];
        $returned = 0;
        $truncated = false;
        $skipped = 0;
        $this->treeDepthProbes = 0;
        $this->appendTree($agent, $path, $budget, $lines, 0, '', $returned, $truncated, $skipped, $args->has('d'), $args->has('a'), $args->value('-I'), $args->value('-P'));
        if ($truncated) {
            $line = VfsText::listingTruncationLine($returned, 'entries', null, 'maxEntries/maxDepth');
            if ($this->displayingStdout) {
                $lines[] = $line;
            } else {
                $this->noteTruncation("tree {$line}");
            }
        }

        return [
            'stdout' => $this->linesOut($lines),
            'cwd' => $cwd,
            'class' => 'browse',
            'returned' => $returned,
            'limit' => $budget->maxEntries,
            'max_depth' => $budget->maxDepth,
            'truncated' => $truncated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendTree(User $agent, string $path, VfsBudget $budget, array &$lines, int $depth, string $prefix, int &$returned, bool &$truncated, int &$skipped, bool $directoriesOnly, bool $showHidden, ?string $ignore, ?string $pattern): void
    {
        if ($returned >= $budget->maxEntries) {
            $truncated = true;

            return;
        }
        if ($depth >= $budget->maxDepth) {
            if (++$this->treeDepthProbes > 25) {
                $truncated = true;

                return;
            }
            try {
                $truncated = $truncated || $this->vfs->listBounded($agent, $path, new VfsBudget(maxEntries: 1)) !== [];
            } catch (VfsError) {
                $skipped++;
            }

            return;
        }

        try {
            $remaining = max(0, $budget->maxEntries - $returned);
            $entries = $this->vfs->listBounded($agent, $path, $budget->with(['maxEntries' => min(max(1, $remaining + 1), 1_000)]));
        } catch (VfsError) {
            $skipped++;

            return;
        }
        if (count($entries) > $remaining) {
            $truncated = true;
            $entries = array_slice($entries, 0, $remaining);
        }

        $entries = array_values(array_filter($entries, function ($entry) use ($directoriesOnly, $showHidden, $ignore, $pattern): bool {
            if (! $showHidden && str_starts_with($entry->name, '.')) {
                return false;
            }
            if ($directoriesOnly && $entry->type !== 'directory') {
                return false;
            }
            if ($ignore !== null && fnmatch($ignore, $entry->name)) {
                return false;
            }

            return $pattern === null || $entry->type === 'directory' || fnmatch($pattern, $entry->name);
        }));

        foreach ($entries as $index => $entry) {
            if ($returned >= $budget->maxEntries) {
                $truncated = true;

                return;
            }
            $last = $index === count($entries) - 1;
            $lines[] = $prefix.($last ? '`-- ' : '|-- ').$entry->name.($entry->type === 'directory' ? '/' : '');
            $returned++;
            if ($entry->type === 'directory') {
                $this->appendTree($agent, $entry->canonicalPath ?? $entry->path, $budget, $lines, $depth + 1, $prefix.($last ? '    ' : '|   '), $returned, $truncated, $skipped, $directoriesOnly, $showHidden, $ignore, $pattern);
            }
        }
    }
}
