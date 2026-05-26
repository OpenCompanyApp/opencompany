<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Shell introspection and path helpers (`which`, `type`, `env`, basename, etc.).
 */
trait ExecutesVfsPathCommands
{
    private function pathTransform(array $tokens, string $cwd, string $kind): array
    {
        $this->assertSupportedOptions($tokens, $kind);
        $path = $this->path($tokens[0] ?? '.', $cwd);
        $stdout = match ($kind) {
            'dirname' => dirname($path) === '\\' ? '/' : dirname($path),
            'basename' => basename($path),
            default => $path,
        };

        return ['stdout' => $stdout, 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function which(array $tokens, string $cwd): array
    {
        $this->assertSupportedOptions($tokens, 'which');
        if (count($tokens) !== 1) {
            throw VfsError::invalid('which requires exactly one command name.');
        }
        $name = $tokens[0] ?? throw VfsError::invalid('which requires a command name.');
        $canonical = $this->canonicalCommandName($name);
        if ($canonical === null) {
            throw VfsError::notFound($name);
        }

        return ['stdout' => $canonical, 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function type(array $tokens, string $cwd): array
    {
        $this->assertSupportedOptions($tokens, 'type');
        if (count($tokens) !== 1) {
            throw VfsError::invalid('type requires exactly one command name.');
        }
        $name = $tokens[0] ?? throw VfsError::invalid('type requires a command name.');
        $canonical = $this->canonicalCommandName($name);
        if ($canonical === null) {
            throw VfsError::notFound($name);
        }

        $description = self::commandCatalog()[$canonical]['description'] ?? 'VFS command.';

        return ['stdout' => "{$name} is a VFS command ({$canonical}): {$description}", 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function commandBuiltin(array $tokens, string $cwd): array
    {
        if (($tokens[0] ?? null) !== '-v') {
            throw VfsError::unsupported('Only command -v is supported by the VFS command builtin.');
        }

        return $this->which(array_slice($tokens, 1), $cwd);
    }

    private function env(User $agent, array $tokens, string $cwd): array
    {
        $this->assertSupportedOptions($tokens, 'env');
        if (isset($tokens[0]) && str_contains((string) $tokens[0], '=')) {
            throw VfsError::unsupported('env assignment and command execution forms are not supported in VFS commands.');
        }

        if (count($tokens) > 1) {
            throw VfsError::invalid('env accepts at most one variable name.');
        }
        $values = [
            'VFS' => 'OpenCompany',
            'PWD' => $cwd,
            'WORKSPACE_ID' => (string) $agent->workspace_id,
            'AGENT_ID' => (string) $agent->id,
        ];

        if (($tokens[0] ?? null) !== null) {
            $key = (string) $tokens[0];

            return ['stdout' => $values[$key] ?? '', 'cwd' => $cwd, 'class' => 'browse'];
        }

        return [
            'stdout' => implode("\n", array_map(fn (string $key, string $value): string => "{$key}={$value}", array_keys($values), $values)),
            'cwd' => $cwd,
            'class' => 'browse',
        ];
    }
}
