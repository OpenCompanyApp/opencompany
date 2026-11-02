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
        $this->assertSupportedOptionsForCommand($tokens, 'diff');
        if (in_array('-e', $tokens, true)) {
            throw VfsError::unsupported('diff -e editor scripts are not supported.');
        }
        $flags = $this->shortFlags($tokens, ['i', 'w', 'b', 'u', 'q', 'r']);
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('diff requires two paths.');
        }

        $a = VfsText::lines($this->normalizeDiffText($this->vfs->read($agent, $paths[0], $budget), $flags));
        $b = VfsText::lines($this->normalizeDiffText($this->vfs->read($agent, $paths[1], $budget), $flags));
        if (($flags['q'] ?? false) && $a !== $b) {
            return ['stdout' => "Files {$paths[0]} and {$paths[1]} differ", 'cwd' => $cwd, 'class' => 'read-transform'];
        }
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
        $this->assertSupportedOptionsForCommand($tokens, 'cmp');
        $flags = $this->shortFlags($tokens, ['l', 's', 'b']);
        $limit = $this->integerOptionValue($tokens, ['-n'], 'cmp', 'byte count');
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('cmp requires two paths.');
        }

        $left = $this->vfs->read($agent, $paths[0], $budget);
        $right = $this->vfs->read($agent, $paths[1], $budget);
        if ($limit !== null) {
            $left = substr($left, 0, $limit);
            $right = substr($right, 0, $limit);
        }
        $same = $left === $right;
        if ($same || ($flags['s'] ?? false)) {
            return ['stdout' => '', 'cwd' => $cwd, 'class' => 'read-transform'];
        }
        if ($flags['l'] ?? false) {
            $out = [];
            $max = max(strlen($left), strlen($right));
            for ($i = 0; $i < $max; $i++) {
                if (($left[$i] ?? null) !== ($right[$i] ?? null)) {
                    $out[] = ($i + 1).' '.ord($left[$i] ?? "\0").' '.ord($right[$i] ?? "\0");
                }
            }

            return ['stdout' => implode("\n", $out), 'cwd' => $cwd, 'class' => 'read-transform'];
        }

        return ['stdout' => "{$paths[0]} {$paths[1]} differ", 'cwd' => $cwd, 'class' => 'read-transform'];
    }

    private function comm(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'comm');
        $flags = $this->shortFlags($tokens, ['1', '2', '3']);
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('comm requires two paths.');
        }

        $a = array_values(array_unique(VfsText::lines($this->vfs->read($agent, $paths[0], $budget))));
        $b = array_values(array_unique(VfsText::lines($this->vfs->read($agent, $paths[1], $budget))));
        $onlyA = array_diff($a, $b);
        $onlyB = array_diff($b, $a);
        $both = array_intersect($a, $b);
        $out = [];
        if (! ($flags['1'] ?? false)) {
            array_push($out, ...$onlyA);
        }
        if (! ($flags['2'] ?? false)) {
            array_push($out, ...array_map(fn (string $line): string => "\t".$line, $onlyB));
        }
        if (! ($flags['3'] ?? false)) {
            array_push($out, ...array_map(fn (string $line): string => "\t\t".$line, $both));
        }

        return ['stdout' => implode("\n", $out), 'cwd' => $cwd, 'class' => 'read-transform'];
    }

    private function checksum(User $agent, array $tokens, string $cwd, VfsBudget $budget, string $cmd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, $cmd);
        $algo = match ($cmd) {
            'sha1sum' => 'sha1',
            'md5sum' => 'md5',
            default => 'sha256',
        };
        $lines = [];
        if ($cmd === 'sha256sum' && in_array('-c', $tokens, true)) {
            $content = $this->vfs->read($agent, $this->pathArgs($tokens, $cwd)[0] ?? $cwd, $budget);
            foreach (VfsText::lines($content) as $line) {
                if (preg_match('/^([a-f0-9]{64})\s+\*?(.+)$/i', $line, $matches) !== 1) {
                    continue;
                }
                $path = $this->path(trim($matches[2]), $cwd);
                $ok = hash('sha256', $this->vfs->read($agent, $path, $budget)) === strtolower($matches[1]);
                $lines[] = "{$path}: ".($ok ? 'OK' : 'FAILED');
            }

            return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'read-transform'];
        }
        foreach ($this->pathArgs($tokens, $cwd) ?: [$cwd] as $path) {
            $lines[] = hash($algo, $this->vfs->read($agent, $path, $budget)).'  '.$path;
        }

        return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'read-transform'];
    }

    private function normalizeDiffText(string $value, array $flags): string
    {
        if ($flags['i'] ?? false) {
            $value = strtolower($value);
        }
        if ($flags['w'] ?? false) {
            $value = preg_replace('/\s+/', '', $value) ?? $value;
        } elseif ($flags['b'] ?? false) {
            $value = preg_replace('/[ \t]+/', ' ', $value) ?? $value;
        }

        return $value;
    }
}
