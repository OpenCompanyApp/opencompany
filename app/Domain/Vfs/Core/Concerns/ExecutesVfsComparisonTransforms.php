<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Comparison and checksum transforms for readable virtual files.
 */
trait ExecutesVfsComparisonTransforms
{
    private function diff(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('diff requires two paths.');
        }

        $a = VfsText::lines($this->vfs->read($agent, $paths[0], $budget));
        $b = VfsText::lines($this->vfs->read($agent, $paths[1], $budget));
        $lines = [];
        foreach ($a as $i => $line) {
            if (($b[$i] ?? null) !== $line) {
                $lines[] = '- '.$line;
                if (isset($b[$i])) {
                    $lines[] = '+ '.$b[$i];
                }
            }
        }
        for ($i = count($a); $i < count($b); $i++) {
            $lines[] = '+ '.$b[$i];
        }

        return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'read-transform'];
    }

    private function cmp(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('cmp requires two paths.');
        }

        $same = $this->vfs->read($agent, $paths[0], $budget) === $this->vfs->read($agent, $paths[1], $budget);

        return ['stdout' => $same ? '' : "{$paths[0]} {$paths[1]} differ", 'cwd' => $cwd, 'class' => 'read-transform'];
    }

    private function comm(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('comm requires two paths.');
        }

        $a = array_unique(VfsText::lines($this->vfs->read($agent, $paths[0], $budget)));
        $b = array_unique(VfsText::lines($this->vfs->read($agent, $paths[1], $budget)));

        return ['stdout' => implode("\n", array_intersect($a, $b)), 'cwd' => $cwd, 'class' => 'read-transform'];
    }

    private function checksum(User $agent, array $tokens, string $cwd, VfsBudget $budget, string $cmd): array
    {
        $algo = match ($cmd) {
            'sha1sum' => 'sha1',
            'md5sum' => 'md5',
            default => 'sha256',
        };
        $lines = [];
        foreach ($this->pathArgs($tokens, $cwd) ?: [$cwd] as $path) {
            $lines[] = hash($algo, $this->vfs->read($agent, $path, $budget)).'  '.$path;
        }

        return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'read-transform'];
    }
}
