<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Write and destructive commands for the VFS shell executor.
 *
 * Every method in this trait must pass through assertWriteCommandAllowed before
 * mutating virtual state, keeping unix-like write syntax aligned with the same
 * approval and capability model as the direct VFS tools.
 */
trait ExecutesVfsMutationCommands
{
    private function mkdir(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptions($tokens, 'mkdir', ['p']);
        $path = $this->firstPathArg($tokens, $cwd) ?? throw VfsError::invalid('mkdir requires a path.');

        return ['stdout' => json_encode($this->vfs->makeDirectory($agent, $path), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function touch(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptions($tokens, 'touch');
        $path = $this->firstPathArg($tokens, $cwd) ?? throw VfsError::invalid('touch requires a path.');

        return ['stdout' => json_encode($this->vfs->touch($agent, $path), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function copy(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptions($tokens, 'cp');
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('cp requires source and destination paths.');
        }

        return ['stdout' => json_encode($this->vfs->copy($agent, $paths[0], $paths[1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function move(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptions($tokens, 'mv');
        $paths = $this->pathArgs($tokens, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('mv requires source and destination paths.');
        }

        return ['stdout' => json_encode($this->vfs->move($agent, $paths[0], $paths[1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function remove(User $agent, array $tokens, string $cwd): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptions($tokens, 'rm');
        $path = $this->firstPathArg($tokens, $cwd) ?? throw VfsError::invalid('rm requires a path.');

        return ['stdout' => json_encode($this->vfs->remove($agent, $path), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'destructive'];
    }

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

    private function tee(User $agent, array $tokens, string $cwd, ?string $stdin): array
    {
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $this->assertSupportedOptions($tokens, 'tee', ['a']);
        $append = in_array('-a', $tokens, true);
        $path = $this->firstPathArg($tokens, $cwd) ?? throw VfsError::invalid('tee requires a destination path.');
        $content = $stdin ?? '';
        if ($append) {
            try {
                $content = $this->vfs->read($agent, $path).$content;
            } catch (VfsError $e) {
                if ($e->errorCode !== 'not_found') {
                    throw $e;
                }
            }
        }

        $result = $this->vfs->write($agent, $path, $content, 'overwrite');

        return ['stdout' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'cwd' => $cwd, 'class' => 'write'];
    }

    private function assertWriteCommandAllowed(User $agent, string $toolSlug): void
    {
        $decision = $this->permissions->resolveToolPermission($agent, $toolSlug, 'write');
        if (! ($decision['allowed'] ?? false)) {
            throw VfsError::permissionDenied('/', "tool {$toolSlug} is denied");
        }
        if (($decision['requires_approval'] ?? false) === true) {
            throw VfsError::approvalRequired("Command requires approval through {$toolSlug}; use the direct tool so the approval wrapper can capture the request.", [
                'tool' => $toolSlug,
            ]);
        }
    }

    private function applyRedirections(User $agent, array $result, array $redirections): array
    {
        foreach ($redirections as $redirect) {
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
            if ($redirect['mode'] === 'append') {
                try {
                    $content = $this->vfs->read($agent, $redirect['path']).$content;
                } catch (VfsError $e) {
                    if ($e->errorCode !== 'not_found') {
                        throw $e;
                    }
                }
            }

            $this->vfs->write($agent, $redirect['path'], $content, 'overwrite');
            $result['stdout'] = '';
            $result['redirected_to'] = $redirect['path'];
        }

        return $result;
    }
}
