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
        $this->assertSupportedOptions($tokens, 'stat');
        $stats = [];
        foreach ($this->pathArgs($tokens, $cwd) ?: [$cwd] as $path) {
            $stats[] = $this->vfs->stat($agent, $path);
        }

        return ['stdout' => json_encode(count($stats) === 1 ? $stats[0] : $stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function file(User $agent, array $tokens, string $cwd): array
    {
        $this->assertSupportedOptions($tokens, 'file');
        $lines = [];
        foreach ($this->pathArgs($tokens, $cwd) ?: [$cwd] as $path) {
            $stat = $this->vfs->stat($agent, $path);
            $mime = $stat['metadata']['mime_type']
                ?? $stat['metadata']['content_format']
                ?? $stat['type']
                ?? 'virtual';
            $lines[] = "{$path}: {$mime}";
        }

        return ['stdout' => implode("\n", $lines), 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function du(User $agent, array $tokens, string $cwd, VfsBudget $budget): array
    {
        $this->assertSupportedOptions($tokens, 'du');
        $path = $this->firstPathArg($tokens, $cwd) ?? $cwd;
        $bytes = strlen($this->vfs->read($agent, $path, $budget));

        return ['stdout' => "{$bytes}\t{$path}", 'cwd' => $cwd, 'class' => 'read'];
    }
}
