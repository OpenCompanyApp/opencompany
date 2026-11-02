<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Models\User;

/**
 * Metadata and size commands that inspect readable VFS paths.
 */
trait ExecutesVfsMetadataCommands
{
    private function stat(User $agent, array $tokens, string $cwd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'stat');
        $format = $this->optionValue($tokens, ['-c', '-f', '--format']);
        $stats = [];
        foreach ($this->pathArgsWithValueOptions($tokens, $cwd, ['-c', '-f', '--format']) ?: [$cwd] as $path) {
            $stats[] = $this->vfs->stat($agent, $path);
        }
        if ($format !== null) {
            $lines = array_map(fn (array $stat): string => $this->formatStat($format, $stat), $stats);

            return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'browse'];
        }

        return ['stdout' => json_encode(count($stats) === 1 ? $stats[0] : $stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function file(User $agent, array $tokens, string $cwd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'file');
        $flags = $this->shortFlags($tokens, ['b', 'i']);
        $brief = ($flags['b'] ?? false) || in_array('--brief', $tokens, true);
        $mimeOnly = ($flags['i'] ?? false) || in_array('--mime', $tokens, true);
        $lines = [];
        $paths = array_values(array_filter($tokens, fn (string $token): bool => ! in_array($token, ['-b', '-i', '--brief', '--mime'], true) && ! str_starts_with($token, '-')));
        foreach (array_map(fn (string $path): string => $this->path($path, $cwd), $paths ?: [$cwd]) as $path) {
            $stat = $this->vfs->stat($agent, $path);
            $mime = $stat['metadata']['mime_type']
                ?? $stat['metadata']['content_format']
                ?? $stat['type']
                ?? 'virtual';
            $value = $mimeOnly ? (string) $mime : (string) $mime;
            $lines[] = $brief ? $value : "{$path}: {$value}";
        }

        return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function du(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'du');
        $flags = $this->shortFlags($tokens, ['h', 's', 'a', 'c']);
        $maxDepth = $this->integerOptionValue($tokens, ['--max-depth'], 'du', 'max depth');
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, ['--max-depth']) ?: [$cwd];
        $lines = [];
        $total = 0;
        foreach ($paths as $path) {
            [$bytes, $pathLines] = $this->duCollect($agent, $path, $budget, $flags['a'] ?? false, $maxDepth, 0);
            $total += $bytes;
            if (($flags['s'] ?? false) || (! ($flags['a'] ?? false) && $maxDepth === null)) {
                $lines[] = $this->formatDuSize($bytes, $flags['h'] ?? false)."\t{$path}";
            } else {
                array_push($lines, ...array_map(fn (array $line): string => $this->formatDuSize($line['bytes'], $flags['h'] ?? false)."\t{$line['path']}", $pathLines));
            }
        }
        if ($flags['c'] ?? false) {
            $lines[] = $this->formatDuSize($total, $flags['h'] ?? false)."\ttotal";
        }

        return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'read'];
    }

    private function formatStat(string $format, array $stat): string
    {
        return strtr($format, [
            '%n' => (string) ($stat['path'] ?? ''),
            '%s' => (string) ($stat['size'] ?? $stat['metadata']['size'] ?? 0),
            '%F' => (string) ($stat['type'] ?? ''),
            '%y' => (string) ($stat['updated_at'] ?? $stat['version'] ?? ''),
            '%m' => '/',
            '%i' => (string) ($stat['backend_id'] ?? ''),
            '%%' => '%',
        ]);
    }

    /**
     * @return array{0: int, 1: list<array{bytes: int, path: string}>}
     */
    private function duCollect(User $agent, string $path, VfsBudget $budget, bool $includeFiles, ?int $maxDepth, int $depth): array
    {
        try {
            $stat = $this->vfs->stat($agent, $path);
            if (($stat['type'] ?? null) === 'file') {
                $bytes = strlen($this->vfs->read($agent, $path, $budget));
                $lines = $includeFiles ? [['bytes' => $bytes, 'path' => $path]] : [];

                return [$bytes, $lines];
            }
            $total = 0;
            $lines = [];
            foreach ($this->vfs->listBounded($agent, $path, new VfsBudget(maxEntries: $budget->maxEntries, maxDepth: 1, maxFiles: $budget->maxFiles)) as $entry) {
                $entryPath = $entry->canonicalPath ?? $entry->path;
                [$bytes, $childLines] = $this->duCollect($agent, $entryPath, $budget, $includeFiles, $maxDepth, $depth + 1);
                $total += $bytes;
                if ($includeFiles && $entry->type === 'file' && $childLines === []) {
                    $childLines[] = ['bytes' => $bytes, 'path' => $entryPath];
                }
                foreach ($childLines as $line) {
                    $lines[] = $line;
                }
            }
            if ($maxDepth === null || $depth <= $maxDepth) {
                array_unshift($lines, ['bytes' => $total, 'path' => $path]);
            }

            return [$total, $lines];
        } catch (\Throwable) {
            return [0, []];
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
