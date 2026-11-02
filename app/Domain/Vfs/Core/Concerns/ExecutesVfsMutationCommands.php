<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPath;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Write and destructive commands for the VFS shell executor.
 *
 * Every method in this trait must pass through assertWriteCommandAllowed before
 * mutating virtual state, keeping unix-like write syntax aligned with the same
 * approval and capability model as the direct VFS tools.
 */
trait ExecutesVfsMutationCommands
{
    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function mkdir(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'mkdir');
        $verbose = $this->shortFlags($tokens, ['p', 'v'])['v'] ?? false;
        $path = $this->firstPathArg($tokens, $cwd) ?? throw VfsError::invalid('mkdir requires a path.');
        $result = $this->vfs->makeDirectory($agent, $path);

        return ['stdout' => $verbose ? "mkdir: {$result['status']} {$result['path']}" : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function touch(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'touch');
        if ($this->optionWasProvided($tokens, ['-d', '-r'])) {
            throw VfsError::unsupported('touch -d and -r are not supported because VFS does not expose manual mtime changes.');
        }
        $noCreate = $this->shortFlags($tokens, ['c'])['c'] ?? false;
        $path = $this->firstPathArg($tokens, $cwd) ?? throw VfsError::invalid('touch requires a path.');
        if ($noCreate) {
            try {
                $this->vfs->stat($agent, $path);
            } catch (VfsError $e) {
                if ($e->errorCode === 'not_found') {
                    return ['stdout' => '', 'cwd' => $cwd, 'class' => 'write'];
                }
                throw $e;
            }
        }

        return ['stdout' => json_encode($this->vfs->touch($agent, $path), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function copy(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'cp');
        $flags = $this->shortFlags($tokens, ['r', 'R', 'a', 'f', 'n', 'v']);
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('cp requires source and destination paths.');
        }
        if ($flags['n'] ?? false) {
            try {
                $this->vfs->stat($agent, $paths[1]);

                return ['stdout' => '', 'cwd' => $cwd, 'class' => 'write'];
            } catch (VfsError $e) {
                if ($e->errorCode !== 'not_found') {
                    throw $e;
                }
            }
        }
        if ($flags['f'] ?? false) {
            $this->removeExistingFileDestination($agent, $paths[1]);
        }

        return ['stdout' => json_encode($this->vfs->copy($agent, $paths[0], $paths[1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function move(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'mv');
        $flags = $this->shortFlags($tokens, ['f', 'n', 'v']);
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('mv requires source and destination paths.');
        }
        if ($flags['n'] ?? false) {
            try {
                $this->vfs->stat($agent, $paths[1]);

                return ['stdout' => '', 'cwd' => $cwd, 'class' => 'write'];
            } catch (VfsError $e) {
                if ($e->errorCode !== 'not_found') {
                    throw $e;
                }
            }
        }
        $force = ($flags['f'] ?? false) === true;
        if ($force && ! hash_equals($paths[0], $paths[1])) {
            $this->removeExistingFileDestination($agent, $paths[1]);
        }

        return ['stdout' => json_encode($this->vfs->move($agent, $paths[0], $paths[1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function remove(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'rm');
        $flags = $this->shortFlags($tokens, ['r', 'R', 'f', 'v', 'd']);
        $path = $this->firstPathArg($tokens, $cwd) ?? throw VfsError::invalid('rm requires a path.');
        try {
            $stat = $this->vfs->stat($agent, $path);
        } catch (VfsError $e) {
            if (($flags['f'] ?? false) && $e->errorCode === 'not_found') {
                return ['stdout' => '', 'cwd' => $cwd, 'class' => 'destructive'];
            }
            throw $e;
        }
        if (($stat['type'] ?? null) === 'directory' && ! (($flags['r'] ?? false) || ($flags['R'] ?? false) || ($flags['d'] ?? false))) {
            throw VfsError::unsupported('rm directory removal requires -r, -R, or -d.');
        }

        return ['stdout' => json_encode($this->vfs->remove($agent, $path), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'destructive'];
    }

    private function removeExistingFileDestination(User $agent, string $path): void
    {
        try {
            $stat = $this->vfs->stat($agent, $path);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return;
            }

            throw $e;
        }
        if (($stat['type'] ?? null) === 'directory') {
            return;
        }

        $this->vfs->remove($agent, $path);
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function mktemp(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'mktemp');
        $directory = $this->optionValue($tokens, ['-p']);
        $template = null;
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token === '-p') {
                $i++;

                continue;
            }
            if (str_starts_with($token, '-p') && strlen($token) > 2) {
                continue;
            }
            if (str_starts_with($token, '-')) {
                continue;
            }
            $template = $token;
        }

        $directory = $directory === null ? '/files/tmp' : $this->path($directory, $cwd);
        if (! str_starts_with($directory, '/files')) {
            throw VfsError::notWritable($directory);
        }
        $template ??= 'tmp.XXXXXXXXXX';
        if (! str_contains($template, 'XXX')) {
            $template .= '.XXXXXXXX';
        }

        $makeDirectory = $this->shortFlags($tokens, ['d', 't'])['d'] ?? false;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $name = preg_replace_callback('/X{3,}/', fn (array $matches): string => Str::random(strlen($matches[0])), $template) ?? $template;
            $path = rtrim($directory, '/').'/'.$name;
            try {
                $this->vfs->stat($agent, $path);

                continue;
            } catch (VfsError $e) {
                if ($e->errorCode !== 'not_found') {
                    throw $e;
                }
            }

            $makeDirectory
                ? $this->vfs->makeDirectory($agent, $path)
                : $this->vfs->write($agent, $path, '', 'create', permissionAlreadyChecked: true);

            return ['stdout' => $path, 'cwd' => $cwd, 'class' => 'write'];
        }

        throw VfsError::invalid('mktemp could not allocate a unique path.');
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function truncate(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptions($tokens, 'truncate', [], ['-s', '--size']);
        $size = $this->optionValue($tokens, ['-s', '--size']) ?? $this->optionValueWithInline($tokens, '-s');
        if (! $this->optionWasProvided($tokens, ['-s', '--size']) && $this->optionValueWithInline($tokens, '-s') === null) {
            throw VfsError::invalid('truncate requires -s 0 or --size=0.');
        }
        if ($size === null || ! preg_match('/^0+$/', $size)) {
            throw VfsError::unsupported('VFS truncate currently supports only size 0.');
        }
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, ['-s', '--size']);
        $path = $paths[0] ?? throw VfsError::invalid('truncate requires a path.');

        return ['stdout' => json_encode($this->vfs->truncate($agent, $path), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function tee(User $agent, array $tokens, string $cwd, ?string $stdin): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptionsForCommand($tokens, 'tee');
        $append = in_array('-a', $tokens, true);
        $paths = $this->pathArgsWithValueOptions($tokens, $cwd, []);
        $path = $paths[0] ?? throw VfsError::invalid('tee requires a destination path.');
        $content = $stdin ?? '';
        $this->assertNotPersistingTruncatedOutput($content, $path);
        if ($append) {
            $content = $this->existingContentForAppend($agent, $path).$content;
        }

        $result = $this->vfs->write($agent, $path, $content, 'overwrite', permissionAlreadyChecked: true);

        return ['stdout' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function assertWriteCommandAllowed(User $agent, string $toolSlug): void
    {
        $this->mutationPolicy->assertAllowedOrRequestApproval(
            agent: $agent,
            directToolSlug: 'vfs_exec',
            operation: 'exec_write',
            // Must match ApprovalExecutionService's replay subject exactly.
            subject: VfsPath::commandSubject($this->currentCommand),
            requiredTools: ['vfs_exec', $toolSlug],
            parameters: $this->currentToolParameters,
            channelId: isset($this->currentToolParameters['channelId']) && is_string($this->currentToolParameters['channelId'])
                ? $this->currentToolParameters['channelId']
                : null,
            hint: 'Mutating VFS commands require vfs_write in addition to vfs_exec.',
            preview: [
                'kind' => 'vfs_command',
                'command' => $this->currentCommand,
            ],
        );
    }

    /**
     * Full current content of an append target, or '' when it does not exist.
     *
     * Invariant: append is read-modify-write, so this must be a content read.
     * A display read would persist the "[... truncated]" line and drop every
     * byte past maxBytes.
     */
    private function existingContentForAppend(User $agent, string $path): string
    {
        try {
            return $this->vfs->readContent($agent, $path);
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
        }

        return '';
    }

    /**
     * Refuse to persist command output that carries the display truncation
     * line produced by a budget-truncated read in this execution (e.g.
     * `cat big.md > /files/copy.md` with the default maxBytes). The visible
     * line stays in stdout for agents; it must never become file content.
     *
     * Both conditions are required so a file that merely documents the marker
     * text can still be copied when nothing was truncated.
     */
    private function assertNotPersistingTruncatedOutput(string $content, string $path): void
    {
        if (($this->currentBudget?->truncations() ?? 0) > 0 && VfsText::hasTruncationMarker($content)) {
            throw VfsError::invalid("Refusing to write truncated output to {$path}: an input read in this command was cut at maxBytes.", [
                'path' => $path,
                'hint' => 'Raise maxBytes so the read is complete, or use cp to copy whole files.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array{stream: string, mode: string, path: string}>  $redirections
     * @return array<string, mixed>
     */
    private function applyRedirections(User $agent, array $result, array $redirections): array
    {
        foreach ($redirections as $redirect) {
            if ($redirect['stream'] === 'stdin') {
                continue;
            }
            if ($redirect['mode'] === 'merge') {
                if ($redirect['stream'] === 'stdout' && $redirect['path'] === '&2') {
                    $result['stderr'] = ($result['stderr'] ?? '').(string) ($result['stdout'] ?? '');
                    $result['stdout'] = '';
                }

                continue;
            }
            if ($redirect['stream'] === 'stderr') {
                if ($redirect['path'] === '/dev/null') {
                    continue;
                }

                throw VfsError::unsupported('stderr file redirection is not supported; use 2>&1 to merge errors into stdout or 2>/dev/null to suppress them.');
            }
            if ($redirect['path'] === '/dev/null') {
                $result['stdout'] = '';

                continue;
            }

            $this->assertWriteCommandAllowed($agent, 'vfs_write');
            $content = (string) ($result['stdout'] ?? '');
            $this->assertNotPersistingTruncatedOutput($content, $redirect['path']);
            if ($redirect['mode'] === 'append') {
                $content = $this->existingContentForAppend($agent, $redirect['path']).$content;
            }

            $this->vfs->write($agent, $redirect['path'], $content, 'overwrite', permissionAlreadyChecked: true);
            $result['stdout'] = '';
            $result['redirected_to'] = $redirect['path'];
        }

        return $result;
    }
}
