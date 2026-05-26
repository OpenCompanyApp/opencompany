<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;

/**
 * Preflight guards for shell syntax that the VFS interpreter intentionally does
 * not emulate.
 */
trait GuardsVfsShellSyntax
{
    private function assertNoUnsupportedShellSyntax(string $command): void
    {
        if (preg_match('/(?:^|[;&|]\s*)find\s+.*\s-exec(?:\s|$)/', $command) === 1) {
            throw VfsError::unsupported('Unsupported find flag: -exec');
        }

        $quote = null;
        $escaped = false;
        $chars = str_split($command);

        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];
            $next = $chars[$i + 1] ?? null;
            if ($escaped) {
                $escaped = false;

                continue;
            }
            if ($char === '\\') {
                $escaped = true;

                continue;
            }
            if (($char === '"' || $char === "'") && $quote === null) {
                $quote = $char;

                continue;
            }
            if ($char === $quote) {
                $quote = null;

                continue;
            }
            if ($quote !== null) {
                continue;
            }
            if ($char === '&' && $next !== '&' && ($chars[$i - 1] ?? null) !== '>' && ($chars[$i - 1] ?? null) !== '&') {
                throw VfsError::unsupported('Background execution with & is not supported in VFS commands.');
            }
            if ($char === '$' && ($next === '(' || (is_string($next) && preg_match('/[A-Za-z_]/', $next) === 1))) {
                throw VfsError::unsupported('Shell variable and command substitution are not supported in VFS commands.');
            }
            if ($char === '`') {
                throw VfsError::unsupported('Backtick command substitution is not supported in VFS commands.');
            }
            if ($char === '<') {
                throw VfsError::unsupported('Input and process substitution are not supported in VFS commands.');
            }
            if ($char === '(' || $char === ')') {
                throw VfsError::unsupported('Subshell syntax is not supported in VFS commands.');
            }
        }
    }
}
