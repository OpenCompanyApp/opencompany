<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Applies one command's redirections with bash's left-to-right fd rules.
 *
 * Owns: resolving where stdout and stderr go (`> f`, `>> f`, `2> f`, `&> f`,
 * `2>&1`, `>&2`, `/dev/null`), delivering the command's stdout and its error
 * message / warnings to those destinations, and writing redirect targets
 * through the governed write path (vfs_exec + vfs_write).
 *
 * Invariants:
 * - Every file target is written via assertWriteCommandAllowed before
 *   OpenCompanyVfs::write; there is no other write path here.
 * - Appends are version-checked read-modify-writes on a full content read,
 *   so a concurrent change fails with `conflict` instead of being lost.
 * - A command that failed never truncates its `>` target: the file is only
 *   written when there is something to write or the command succeeded.
 * - Every file a redirection names is opened, left to right, as in bash:
 *   `printf x > a > b` creates/truncates `a` and writes `x` to `b`; a
 *   superseded `>>` target is created when missing. Each one goes through
 *   the same governed write, so an earlier target the agent may not write
 *   fails the command before later targets are written (VFS deviation:
 *   commands run before their redirections are delivered, so the command
 *   itself has already run).
 */
trait ResolvesVfsRedirectionEffects
{
    /**
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     * @return list<array{stream: string, mode: string, path: string}>
     */
    private function normalizedRedirections(array $redirections, string $cwd): array
    {
        return array_map(function (array $redirect) use ($cwd): array {
            if ($redirect['mode'] !== 'merge' && $redirect['path'] !== '/dev/null') {
                $redirect['path'] = $this->path($redirect['path'], $cwd);
            }

            return $redirect;
        }, $redirections);
    }

    /**
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     */
    private function stdinRedirectPath(array $redirections): ?string
    {
        $path = null;
        foreach ($redirections as $redirect) {
            if ($redirect['stream'] === 'stdin') {
                $path = $redirect['path'];
            }
        }

        return $path;
    }

    /**
     * Resolve fd 1 and fd 2 after applying redirections left to right.
     * Destinations: 'out' (pipe or display), 'err' (the line's stderr),
     * 'null', or ['file', path, mode].
     *
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     * @return array{stdout: string|array{0: string, 1: string, 2: string}, stderr: string|array{0: string, 1: string, 2: string}}
     */
    private function resolveDescriptors(array $redirections): array
    {
        $fd = ['stdout' => 'out', 'stderr' => 'err'];
        foreach ($redirections as $redirect) {
            if ($redirect['stream'] === 'stdin') {
                continue;
            }
            if ($redirect['mode'] === 'merge') {
                $fd[$redirect['stream']] = $redirect['path'] === '&1' ? $fd['stdout'] : $fd['stderr'];

                continue;
            }
            $fd[$redirect['stream']] = $redirect['path'] === '/dev/null' ? 'null' : ['file', $redirect['path'], $redirect['mode']];
        }

        return $fd;
    }

    /**
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     */
    private function stdoutReachesDisplay(array $redirections): bool
    {
        return $this->resolveDescriptors($redirections)['stdout'] === 'out';
    }

    /**
     * Deliver a finished command's streams.
     *
     * @param  array<string, mixed>|null  $result
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     * @return array{stdout: string, stderr: string, error_handled: bool}
     */
    private function applyRedirections(User $agent, ?array $result, array $redirections, ?VfsError $error): array
    {
        $fd = $this->resolveDescriptors($redirections);
        $stdoutText = (string) ($result['stdout'] ?? '');
        $stderrText = trim(($error !== null ? $error->getMessage()."\n" : '').(string) ($result['stderr'] ?? ''), "\n");
        if ($stderrText !== '') {
            $stderrText .= "\n";
        }
        // The error itself is reported by the executor (as stderr or as the
        // envelope) unless stderr is redirected away from the line's stderr.
        $errorHandled = $error !== null && $fd['stderr'] !== 'err';

        $out = '';
        $err = '';
        $files = [];
        foreach ([[$stdoutText, $fd['stdout'], 'stdout'], [$stderrText, $fd['stderr'], 'stderr']] as [$text, $destination, $stream]) {
            if ($destination === 'out') {
                $out .= $text;
            } elseif ($destination === 'err') {
                // The error message itself is reported by the executor (as
                // stderr or as the envelope); only warnings are added here.
                $err .= $stream === 'stderr' ? (string) ($result['stderr'] ?? '') : $text;
            } elseif (is_array($destination)) {
                $key = $destination[1];
                $files[$key] ??= ['mode' => $destination[2], 'content' => ''];
                $files[$key]['content'] .= $text;
            }
        }

        // Invariant: targets are opened in the order the redirections name
        // them, including targets a later redirection superseded.
        foreach ($this->redirectTargetsInOrder($redirections) as $path => $openMode) {
            $file = $files[$path] ?? ['mode' => $openMode, 'content' => ''];
            if ($error !== null && $file['content'] === '') {
                continue;
            }
            $this->writeRedirectTarget($agent, $path, $file['content'], $file['mode']);
        }

        return ['stdout' => $out, 'stderr' => $err, 'error_handled' => $errorHandled];
    }

    /**
     * Every file target named by an output redirection, in first-appearance
     * order, with the mode it was opened with ('overwrite' or 'append').
     *
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     * @return array<string, string>
     */
    private function redirectTargetsInOrder(array $redirections): array
    {
        $targets = [];
        foreach ($redirections as $redirect) {
            if ($redirect['stream'] === 'stdin' || $redirect['mode'] === 'merge' || $redirect['path'] === '/dev/null') {
                continue;
            }
            $targets[$redirect['path']] ??= $redirect['mode'];
        }

        return $targets;
    }

    /**
     * Write redirected output to a VFS path through the governed write path.
     */
    private function writeRedirectTarget(User $agent, string $path, string $content, string $mode): void
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertNotPersistingTruncatedOutput($content, $path);
        $version = null;
        if ($mode === 'append') {
            [$existing, $version] = $this->existingContentForAppend($agent, $path);
            if ($content === '' && $version !== null) {
                // Opening an existing file for append with nothing to add
                // leaves it unchanged (no new version).
                return;
            }
            $content = $existing.$content;
        }

        $this->vfs->write($agent, $path, $content, 'overwrite', $version, permissionAlreadyChecked: true);
    }
}
