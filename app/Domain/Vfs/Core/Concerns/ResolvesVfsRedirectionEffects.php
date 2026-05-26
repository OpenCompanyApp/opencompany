<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Resolves stderr/stdout redirection effects after token-level parsing.
 *
 * Redirection parsing identifies operators; this concern applies the shell-like
 * left-to-right stream rules that matter for error propagation and write
 * targets without mixing that policy into the command executor loop.
 */
trait ResolvesVfsRedirectionEffects
{
    /**
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     */
    private function hasUnsupportedStderrFileRedirect(array $redirections): bool
    {
        return collect($redirections)->contains(fn (array $redirect): bool => $redirect['stream'] === 'stderr' && $redirect['mode'] !== 'merge' && $redirect['path'] !== '/dev/null');
    }

    /**
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     */
    private function stderrSuppressed(array $redirections): bool
    {
        return collect($redirections)->contains(fn (array $redirect): bool => $redirect['stream'] === 'stderr' && $redirect['path'] === '/dev/null');
    }

    /**
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     */
    private function stderrMergedToStdout(array $redirections): bool
    {
        return $this->stderrDestination($redirections)['kind'] === 'stdout';
    }

    /**
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     * @return array{path: string, mode: string}|null
     */
    private function stderrMergeFile(array $redirections): ?array
    {
        $destination = $this->stderrDestination($redirections);

        return $destination['kind'] === 'file' ? ['path' => $destination['path'], 'mode' => $destination['mode']] : null;
    }

    /**
     * Resolve stderr's effective destination after applying redirections from
     * left to right, matching the shell rule agents expect for `> file 2>&1`.
     *
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     * @return array{kind: 'stderr'|'stdout'|'null'|'file', path?: string, mode?: string}
     */
    private function stderrDestination(array $redirections): array
    {
        $stdout = ['kind' => 'stdout'];
        $stderr = ['kind' => 'stderr'];

        foreach ($redirections as $redirect) {
            if ($redirect['mode'] === 'merge') {
                if ($redirect['stream'] === 'stderr' && $redirect['path'] === '&1') {
                    $stderr = $stdout;
                } elseif ($redirect['stream'] === 'stdout' && $redirect['path'] === '&2') {
                    $stdout = $stderr;
                }

                continue;
            }

            $destination = $redirect['path'] === '/dev/null'
                ? ['kind' => 'null']
                : ['kind' => 'file', 'path' => $redirect['path'], 'mode' => $redirect['mode']];

            if ($redirect['stream'] === 'stdout') {
                $stdout = $destination;
            } else {
                $stderr = $destination;
            }
        }

        return $stderr;
    }

    /**
     * @param  array{path: string, mode: string}  $target
     */
    private function writeRedirectedError(User $agent, array $target, string $message): void
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $content = $message;
        if ($target['mode'] === 'append') {
            try {
                $content = $this->vfs->read($agent, $target['path']).$message;
            } catch (VfsError $e) {
                if ($e->errorCode !== 'not_found') {
                    throw $e;
                }
            }
        }

        $this->vfs->write($agent, $target['path'], $content, 'overwrite');
    }
}
