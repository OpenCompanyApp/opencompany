<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Metadata and size commands: stat, file, du.
 *
 * du is bounded: it visits at most maxFiles entries to maxDepth levels in
 * total (not per directory), takes sizes from metadata and only reads
 * content when a mount reports no size, and ends with a truncation line when
 * a budget stopped it. Errors on individual entries are counted, not hidden.
 */
trait ExecutesVfsMetadataCommands
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function stat(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('stat', $words);
        $format = $args->value(['-c', '-f', '--format']);
        $stats = [];
        foreach ($this->operandPaths($args, $cwd) ?: [$cwd] as $path) {
            $stats[] = $this->vfs->stat($agent, $path);
        }
        if ($format !== null) {
            return ['stdout' => $this->linesOut(array_map(fn (array $stat): string => $this->formatStat($format, $stat), $stats)), 'cwd' => $cwd, 'class' => 'browse'];
        }

        return ['stdout' => json_encode(count($stats) === 1 ? $stats[0] : $stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", 'cwd' => $cwd, 'class' => 'browse'];
    }

    /**
     * @param  array<string, mixed>  $stat
     */
    private function formatStat(string $format, array $stat): string
    {
        return strtr($format, [
            '%n' => (string) ($stat['path'] ?? ''),
            '%N' => "'".(string) ($stat['path'] ?? '')."'",
            '%s' => (string) ($stat['size'] ?? $stat['metadata']['size'] ?? 0),
            '%F' => (string) ($stat['type'] ?? ''),
            '%y' => (string) ($stat['updated_at'] ?? $stat['metadata']['updated_at'] ?? $stat['version'] ?? ''),
            '%i' => (string) ($stat['backend_id'] ?? ''),
            '%m' => '/',
            '%%' => '%',
        ]);
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function file(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('file', $words);
        $lines = [];
        foreach ($this->operandPaths($args, $cwd) ?: [$cwd] as $path) {
            $stat = $this->vfs->stat($agent, $path);
            $metadata = is_array($stat['metadata'] ?? null) ? $stat['metadata'] : [];
            $type = (string) ($stat['type'] ?? 'file');
            $mime = $type === 'directory' ? 'inode/directory' : (string) ($metadata['mime_type'] ?? match ($metadata['content_format'] ?? null) {
                'markdown' => 'text/markdown',
                'json' => 'application/json',
                'ndjson' => 'application/x-ndjson',
                default => 'text/plain',
            });
            // Default: the VFS content format (markdown, json, ...) or mime
            // type; -i: a real mime type with charset, like `file -i`.
            $value = $args->has('i')
                ? $mime.(str_starts_with($mime, 'text/') || str_contains($mime, 'json') ? '; charset=utf-8' : '')
                : (string) ($type === 'directory' ? 'directory' : ($metadata['mime_type'] ?? $metadata['content_format'] ?? $this->describeMime($mime, $type)));
            $lines[] = $args->has('b') ? $value : "{$path}: {$value}";
        }

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function describeMime(string $mime, string $type): string
    {
        if ($type === 'directory') {
            return 'directory';
        }

        return match (true) {
            $mime === 'text/markdown' => 'Markdown text',
            $mime === 'application/json' => 'JSON data',
            $mime === 'application/x-ndjson' => 'NDJSON data',
            $mime === 'text/csv' => 'CSV text',
            $mime === 'text/plain' => 'text',
            str_starts_with($mime, 'text/') => strtoupper(substr($mime, 5)).' text',
            str_starts_with($mime, 'image/') => strtoupper(substr($mime, 6)).' image data',
            $mime === 'application/pdf' => 'PDF document',
            $mime === 'application/zip' => 'Zip archive data',
            $mime === 'application/gzip', $mime === 'application/x-gzip' => 'gzip compressed data',
            default => $mime,
        };
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function du(User $agent, array $words, string $cwd, VfsBudget $budget): array
    {
        $args = $this->args('du', $words);
        $maxDepth = $args->int(['--max-depth', '-d']);
        $all = $args->has('a');
        $summary = $args->has('s');
        $human = $args->has('h');
        $lines = [];
        $total = 0;
        $state = ['visited' => 0, 'truncated' => false, 'skipped' => 0];

        foreach ($this->operandPaths($args, $cwd) ?: [$cwd] as $path) {
            [$bytes, $pathLines] = $this->duCollect($agent, $path, $budget, $all, $summary ? 0 : $maxDepth, 0, $state);
            $total += $bytes;
            if ($summary) {
                $lines[] = $this->formatDuSize($bytes, $human)."\t{$path}";
            } else {
                array_push($lines, ...array_map(fn (array $line): string => $this->formatDuSize($line['bytes'], $human)."\t{$line['path']}", $pathLines));
            }
        }
        if ($args->has('c')) {
            $lines[] = $this->formatDuSize($total, $human)."\ttotal";
        }
        if ($state['truncated']) {
            $line = VfsText::listingTruncationLine($state['visited'], 'entries (sizes are partial)', null, 'maxFiles/maxDepth');
            $this->displayingStdout ? $lines[] = $line : $this->noteTruncation('du '.$line);
        }

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'read', 'truncated' => $state['truncated'], 'skipped' => $state['skipped']];
    }

    /**
     * Post-order size walk. Lines list directories deepest first (GNU order)
     * and files too when $includeFiles.
     *
     * @param  array{visited: int, truncated: bool, skipped: int}  $state
     * @param  array<string, mixed>|null  $entryStat  Entry metadata from the parent listing (avoids a stat per entry).
     * @return array{0: int, 1: list<array{bytes: int, path: string}>}
     */
    private function duCollect(User $agent, string $path, VfsBudget $budget, bool $includeFiles, ?int $printDepth, int $depth, array &$state, ?array $entryStat = null): array
    {
        if ($state['visited'] >= $budget->maxFiles) {
            $state['truncated'] = true;

            return [0, []];
        }
        $state['visited']++;
        try {
            $stat = $entryStat ?? $this->vfs->stat($agent, $path);
        } catch (VfsError $e) {
            if ($depth === 0) {
                throw $e;
            }
            $state['skipped']++;

            return [0, []];
        }
        $printable = $printDepth === null || $depth <= $printDepth;
        if (($stat['type'] ?? null) !== 'directory') {
            $bytes = $this->entryBytes($agent, $path, $stat);

            return [$bytes, ($includeFiles || $depth === 0) && $printable ? [['bytes' => $bytes, 'path' => $path]] : []];
        }
        if ($depth >= $budget->maxDepth) {
            $state['truncated'] = true;

            return [0, $printable ? [['bytes' => 0, 'path' => $path]] : []];
        }

        $total = 0;
        $lines = [];
        try {
            $entries = $this->vfs->listBounded($agent, $path, $budget->with(['maxEntries' => 1_000]));
        } catch (VfsError) {
            $state['skipped']++;
            $entries = [];
        }
        foreach ($entries as $entry) {
            $entryPath = $entry->canonicalPath ?? $entry->path;
            [$bytes, $childLines] = $this->duCollect($agent, $entryPath, $budget, $includeFiles, $printDepth, $depth + 1, $state, $entry->jsonSerialize());
            $total += $bytes;
            array_push($lines, ...$childLines);
            if ($state['truncated'] && $state['visited'] >= $budget->maxFiles) {
                break;
            }
        }
        if ($printable) {
            $lines[] = ['bytes' => $total, 'path' => $path];
        }

        return [$total, $lines];
    }

    /**
     * @param  array<string, mixed>  $stat
     */
    private function entryBytes(User $agent, string $path, array $stat): int
    {
        $size = $stat['size'] ?? $stat['metadata']['size'] ?? null;
        if ($size !== null) {
            return (int) $size;
        }
        try {
            return strlen($this->readFull($agent, $path));
        } catch (VfsError) {
            return 0;
        }
    }

    private function formatDuSize(int $bytes, bool $human): string
    {
        if (! $human) {
            return (string) $bytes;
        }
        $value = (float) $bytes;
        foreach (['B', 'K', 'M', 'G', 'T'] as $unit) {
            if ($value < 1024 || $unit === 'T') {
                return $unit === 'B' ? ((string) (int) $value).'B' : ((string) round($value, 1)).$unit;
            }
            $value /= 1024;
        }

        return (string) $bytes;
    }
}
