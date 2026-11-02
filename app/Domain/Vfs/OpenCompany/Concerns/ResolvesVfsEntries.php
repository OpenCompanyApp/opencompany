<?php

namespace App\Domain\Vfs\OpenCompany\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsEntry;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use App\Models\WorkspaceFile;
use Illuminate\Support\Str;

/**
 * Search traversal and shared formatting helpers for OpenCompanyVfs.
 */
trait ResolvesVfsEntries
{
    /**
     * Readable files under $path for search, as friendly display path =>
     * path to read (canonical when known; the search loop falls back to the
     * display path when a canonical path does not resolve on its own).
     *
     * Directories are walked by their friendly path, like the shell's
     * walkFiles, so child paths come straight from the listing and stay
     * valid friendly paths (names are disambiguated as `name--1a2b3c4d`).
     * Each directory is isolated: a child that fails to resolve or list is
     * skipped without ending the walk of its siblings. Alias entries (/me)
     * are skipped (VfsEntry::isAlias()).
     *
     * @return \Generator<string, string>
     */
    private function walkReadableFiles(User $agent, string $path, VfsBudget $budget, int $depth = 0, ?string $readPath = null): \Generator
    {
        if ($depth > $budget->maxDepth) {
            return;
        }

        try {
            $entry = $this->directEntryForPath($agent, $path) ?? $this->entryForPath($agent, $path);
        } catch (VfsError) {
            return;
        }
        if ($entry?->type === 'file') {
            yield $path => $readPath ?? $path;

            return;
        }

        if ($depth >= $budget->maxDepth) {
            return;
        }

        try {
            $entries = $this->listBounded($agent, $path, $budget);
        } catch (VfsError) {
            return;
        }

        $prefix = rtrim($path, '/').'/';
        foreach ($entries as $child) {
            // Alias mounts (/me) duplicate canonical objects (VfsEntry::isAlias()).
            if ($child->isAlias()) {
                continue;
            }
            $childPath = str_starts_with($child->path, $prefix) ? $child->path : $prefix.$child->name;
            if ($child->type === 'file') {
                yield $childPath => $child->canonicalPath ?? $childPath;
            } elseif ($child->type === 'directory') {
                yield from $this->walkReadableFiles($agent, $childPath, $budget, $depth + 1, $child->canonicalPath);
            }
        }
    }

    /**
     * @param  list<VfsEntry>  $entries
     */
    private function entriesToText(array $entries): string
    {
        return collect($entries)->map(fn (VfsEntry $entry): string => match ($entry->type) {
            'directory' => $entry->name.'/',
            default => $entry->name,
        })->implode("\n");
    }

    private function slug(string $value): string
    {
        return Str::slug($value) ?: rawurlencode($value);
    }

    /**
     * Version token of a workspace file: sha256(updated_at | sha256(bytes))
     * for text files (size instead of bytes for binary files and folders).
     * A content_hash cached by the VFS write path stands in for sha256(bytes),
     * so listing a folder does not read every file; files written elsewhere
     * have no hash and fall back to reading their bytes once.
     */
    private function fileVersion(WorkspaceFile $file): string
    {
        $seed = (string) $file->size;
        if (! $file->is_folder && $this->isTextFile($file) && is_string($file->content_hash) && $file->content_hash !== '') {
            return hash('sha256', ($file->updated_at?->toIso8601String() ?? '').'|'.$file->content_hash);
        }
        if (! $file->is_folder && $this->isTextFile($file)) {
            try {
                $seed = (string) $this->files->readFileContents($file);
            } catch (\Throwable) {
                $seed = (string) $file->size;
            }
        }

        return $this->version($file->updated_at?->toIso8601String(), $seed);
    }

    private function version(?string $updatedAt, string $contentSeed): string
    {
        return hash('sha256', ($updatedAt ?? '').'|'.hash('sha256', $contentSeed));
    }
}
