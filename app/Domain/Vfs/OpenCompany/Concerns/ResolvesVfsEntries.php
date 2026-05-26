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
     * @return \Generator<int, string>
     */
    private function walkReadableFiles(User $agent, string $path, VfsBudget $budget, int $depth = 0): \Generator
    {
        if ($depth > $budget->maxDepth) {
            return;
        }

        $entry = $this->directEntryForPath($agent, $path) ?? $this->entryForPath($agent, $path);
        if ($entry?->type === 'file') {
            yield $path;

            return;
        }

        if ($depth >= $budget->maxDepth) {
            return;
        }

        try {
            $entries = $this->list($agent, $path, $budget);
            foreach ($entries as $entry) {
                if ($entry->type === 'file') {
                    yield $entry->canonicalPath ?? $entry->path;
                } elseif ($entry->type === 'directory') {
                    yield from $this->walkReadableFiles($agent, $entry->canonicalPath ?? $entry->path, $budget, $depth + 1);
                }
            }
        } catch (VfsError) {
            return;
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

    private function fileVersion(WorkspaceFile $file): string
    {
        $seed = (string) $file->size;
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
