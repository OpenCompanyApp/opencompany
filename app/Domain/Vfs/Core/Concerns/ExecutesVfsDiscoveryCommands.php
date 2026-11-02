<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsCommandHelp;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;

/**
 * Command discovery builtins: help/man, which, type, command -v.
 *
 * All answers come from VfsCommandSpecRegistry so discovery cannot drift from
 * what runs. Looking up an unknown name fails with not_found and the message
 * "Unknown VFS command: X. Run `help` to list commands." (never "Path not
 * found"); unlike running an unknown command (unsupported, which aborts the
 * line), a failed lookup lets `which X || ...` branch; `which` marks recognized-but-
 * unsupported commands so an agent never concludes a refused command runs.
 */
trait ExecutesVfsDiscoveryCommands
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function help(array $words, string $cwd): array
    {
        if (count($words) > 1) {
            throw VfsError::invalid('help accepts at most one command name.');
        }
        if (isset($words[0]) && VfsCommandSpecRegistry::canonical($words[0]) === null) {
            throw $this->unknownLookup($words[0]);
        }

        return [
            'stdout' => (isset($words[0]) ? VfsCommandHelp::commandMarkdown($words[0]) : VfsCommandHelp::listMarkdown())."\n",
            'cwd' => $cwd,
            'class' => 'browse',
            'command_spec' => isset($words[0]) ? VfsCommandHelp::commandEntry($words[0]) : null,
        ];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function which(array $words, string $cwd): array
    {
        if ($words === []) {
            throw VfsError::invalid('which requires at least one command name.');
        }
        $lines = [];
        foreach ($words as $name) {
            $canonical = VfsCommandSpecRegistry::canonical($name);
            if ($canonical === null) {
                throw $this->unknownLookup($name);
            }
            $spec = VfsCommandSpecRegistry::get($canonical);
            $lines[] = $spec !== null && $spec->status !== 'implemented'
                ? "{$canonical} (recognized, not supported: {$spec->unsupportedReason})"
                : $canonical;
        }

        return [
            'stdout' => $this->linesOut($lines),
            'cwd' => $cwd,
            'class' => 'browse',
            'command_spec' => count($words) === 1 ? VfsCommandHelp::commandEntry($words[0]) : null,
        ];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function type(array $words, string $cwd): array
    {
        if (count($words) !== 1) {
            throw VfsError::invalid('type requires exactly one command name.');
        }
        $name = $words[0];
        $canonical = VfsCommandSpecRegistry::canonical($name);
        if ($canonical === null) {
            throw $this->unknownLookup($name);
        }
        $spec = VfsCommandSpecRegistry::get($canonical);
        $suffix = $spec?->suggestion !== null ? ' '.$spec->suggestion : '';
        $alias = $canonical !== $name ? " (alias of {$canonical})" : '';

        return [
            'stdout' => "{$name} is a VFS command{$alias}; status={$spec?->status}; class={$spec?->class}.{$suffix}\n",
            'cwd' => $cwd,
            'class' => 'browse',
            'command_spec' => VfsCommandHelp::commandEntry($canonical),
        ];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function commandBuiltin(array $words, string $cwd): array
    {
        if (($words[0] ?? null) !== '-v') {
            throw VfsError::unsupported('Only command -v is supported by the VFS command builtin.', ['suggestion' => 'Use command -v NAME, which NAME, or help NAME.']);
        }

        return $this->which(array_slice($words, 1), $cwd);
    }

    private function unknownLookup(string $name): VfsError
    {
        return new VfsError('not_found', "Unknown VFS command: {$name}. Run `help` to list commands.", [
            'command' => $name,
            'status' => 'unknown',
        ]);
    }
}
