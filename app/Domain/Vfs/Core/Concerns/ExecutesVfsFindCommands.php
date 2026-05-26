<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

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
        $this->assertSupportedOptions($tokens, 'find', [], ['-name', '-type', '-maxdepth', '-maxDepth', '--max-depth'], ['-print']);
        $path = $this->firstPathArg($tokens, $cwd) ?? $cwd;
        $name = $this->optionValue($tokens, ['-name']);
        $type = $this->optionValue($tokens, ['-type']);
        if ($this->optionWasProvided($tokens, ['-name']) && ($name === null || $name === '')) {
            throw VfsError::invalid('find -name requires a pattern.');
        }
        if ($this->optionWasProvided($tokens, ['-type']) && ! in_array($type, ['f', 'd', 'file', 'directory'], true)) {
            throw VfsError::invalid('find -type supports only f/file or d/directory.');
        }

        $maxDepth = $this->integerOptionValue($tokens, ['-maxdepth', '-maxDepth', '--max-depth'], 'find', 'max depth');
        if ($maxDepth !== null) {
            $budget = new VfsBudget($budget->maxEntries, max(0, $maxDepth), $budget->maxBytes, $budget->maxFiles, $budget->maxMatches, $budget->maxLineLength);
        }
        $lines = [];
        $seen = [];
        $scanned = 0;
        $truncated = false;
        $skipped = 0;
        $this->appendFind($agent, $path, $budget, $lines, $seen, $name, $type, 0, $scanned, $truncated, $skipped);

        return [
            'stdout' => implode("\n", $lines),
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

    private function appendFind(User $agent, string $path, VfsBudget $budget, array &$lines, array &$seen, ?string $name, ?string $type, int $depth, int &$scanned, bool &$truncated, int &$skipped): void
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
            $typeMatches = $type === null || ($type === 'f' && $statType === 'file') || ($type === 'd' && $statType === 'directory');
            $nameMatches = $name === null || fnmatch($name, $pathName);
            if ($typeMatches && $nameMatches && ! isset($seen[$path])) {
                $lines[] = $path;
                $seen[$path] = true;
            }
            if ($depth >= $budget->maxDepth || count($lines) >= $budget->maxEntries || $scanned >= $budget->maxFiles) {
                $truncated = count($lines) >= $budget->maxEntries || $scanned >= $budget->maxFiles;

                return;
            }

            $entries = $this->vfs->list($agent, $path, $listBudget);
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
            $typeMatches = $type === null || ($type === 'f' && $entry->type === 'file') || ($type === 'd' && $entry->type === 'directory');
            $nameMatches = $name === null || fnmatch($name, $entry->name) || ($name === '*.md' && $entry->type === 'file' && (($entry->metadata['content_format'] ?? null) === 'markdown'));
            $entryPath = $entry->canonicalPath ?? $entry->path;
            if ($typeMatches && $nameMatches) {
                if (! isset($seen[$entryPath])) {
                    $lines[] = $entryPath;
                    $seen[$entryPath] = true;
                    if (count($lines) >= $budget->maxEntries) {
                        $truncated = true;

                        return;
                    }
                }
            }
            if ($entry->type === 'directory') {
                $this->appendFind($agent, $entryPath, $budget, $lines, $seen, $name, $type, $depth + 1, $scanned, $truncated, $skipped);
            }
        }
    }
}
