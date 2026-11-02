<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsCommandHelp;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;

/**
 * Command discovery builtins for the VFS shell.
 */
trait ExecutesVfsDiscoveryCommands
{
    private function help(array $tokens, string $cwd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'help');
        if (count($tokens) > 1) {
            throw VfsError::invalid('help accepts at most one command name.');
        }

        $stdout = isset($tokens[0])
            ? VfsCommandHelp::commandMarkdown((string) $tokens[0])
            : VfsCommandHelp::listMarkdown();

        return [
            'stdout' => $stdout,
            'cwd' => $cwd,
            'class' => 'browse',
            'command_spec' => isset($tokens[0]) ? VfsCommandHelp::commandEntry((string) $tokens[0]) : null,
        ];
    }

    private function which(array $tokens, string $cwd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'which');
        if (count($tokens) !== 1) {
            throw VfsError::invalid('which requires exactly one command name.');
        }
        $name = $tokens[0] ?? throw VfsError::invalid('which requires a command name.');
        $canonical = $this->canonicalCommandName($name);
        if ($canonical === null) {
            throw VfsError::notFound($name);
        }

        return [
            'stdout' => $canonical,
            'cwd' => $cwd,
            'class' => 'browse',
            'command_spec' => VfsCommandHelp::commandEntry($canonical),
        ];
    }

    private function type(array $tokens, string $cwd): array
    {
        $this->assertSupportedOptionsForCommand($tokens, 'type');
        if (count($tokens) !== 1) {
            throw VfsError::invalid('type requires exactly one command name.');
        }
        $name = $tokens[0] ?? throw VfsError::invalid('type requires a command name.');
        $canonical = $this->canonicalCommandName($name);
        if ($canonical === null) {
            throw VfsError::notFound($name);
        }

        $spec = VfsCommandSpecRegistry::get($canonical);
        $suffix = $spec?->suggestion !== null ? ' '.$spec->suggestion : '';

        return [
            'stdout' => "{$name} is a VFS command ({$canonical}); status={$spec?->status}; class={$spec?->class}.{$suffix}",
            'cwd' => $cwd,
            'class' => 'browse',
            'command_spec' => VfsCommandHelp::commandEntry($canonical),
        ];
    }

    private function commandBuiltin(array $tokens, string $cwd): array
    {
        if (($tokens[0] ?? null) !== '-v') {
            throw VfsError::unsupported('Only command -v is supported by the VFS command builtin.');
        }

        return $this->which(array_slice($tokens, 1), $cwd);
    }
}
