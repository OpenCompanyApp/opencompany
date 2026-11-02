<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Shell introspection and path helpers (`which`, `type`, `env`, basename, etc.).
 */
trait ExecutesVfsPathCommands
{
    private function pathTransform(array $tokens, string $cwd, string $kind): array
    {
        $this->assertSupportedOptionsForCommand($tokens, $kind);
        $paths = $this->pathArgs($tokens, $cwd);
        $path = $paths[0] ?? $this->path('.', $cwd);
        if ($kind === 'realpath' && ($this->shortFlags($tokens, ['e', 'm'])['e'] ?? false)) {
            $this->vfs->stat($this->currentAgent, $path);
        }
        $stdout = match ($kind) {
            'dirname' => dirname($path) === '\\' ? '/' : dirname($path),
            'basename' => basename($path),
            default => $path,
        };

        return ['stdout' => $stdout, 'cwd' => $cwd, 'class' => 'browse'];
    }

    private function readlink(array $tokens, string $cwd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'readlink');
        $flags = $this->shortFlags($tokens, ['f', 'e', 'm', 'n']);
        $args = array_values(array_filter($tokens, fn (string $token): bool => ! in_array($token, ['-f', '-e', '-m', '-n'], true)));
        $path = isset($args[0]) ? $this->path($args[0], $cwd) : throw VfsError::invalid('readlink requires a path.');
        if ($flags['e'] ?? false) {
            $this->vfs->stat($this->currentAgent, $path);
        }

        return ['stdout' => $path.(($flags['n'] ?? false) ? '' : "\n"), 'cwd' => $cwd, 'class' => 'browse'];
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

    private function dateCommand(array $tokens, string $cwd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'date');
        $time = $this->optionValue($tokens, ['-d']);
        $date = $time === null ? now() : Carbon::parse($time);
        if ($this->shortFlags($tokens, ['u', 'I', 'R'])['u'] ?? false) {
            $date = $date->utc();
        }
        $flags = $this->shortFlags($tokens, ['u', 'I', 'R']);
        $stdout = match (true) {
            $flags['I'] ?? false => $date->toDateString(),
            $flags['R'] ?? false => $date->toRfc2822String(),
            default => $date->toIso8601String(),
        };

        return ['stdout' => $stdout, 'cwd' => $cwd, 'class' => 'browse'];
    }
}
