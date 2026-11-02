<?php

namespace App\Domain\Vfs\Core;

/**
 * Pure virtual-path normalization shared by the VFS adapter and approval replay.
 *
 * Invariant: approval subjects are compared by exact string equality between
 * the policy check that created an approval and the replay that consumes it
 * (VfsApprovedExecutionContext). Both sides must therefore normalize through
 * this one function; a second "equivalent" normalizer is how `/docs//x/` used
 * to trigger a fresh approval on every replay. Do not add workspace- or
 * mount-aware logic here — this is syntax only.
 */
final class VfsPath
{
    /**
     * Collapse duplicate slashes, `.` and `..` segments and resolve a relative
     * path against $cwd. The result always starts with `/` and never ends with
     * one (except the root itself).
     */
    public static function normalize(string $path, string $cwd = '/'): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = '.';
        }

        if (! str_starts_with($path, '/')) {
            $path = rtrim($cwd, '/').'/'.$path;
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }

    /**
     * Canonical approval subject for a shell command. The executor records the
     * command exactly as VfsExec received it after trimming; replay must use
     * the same rule so the approval matches.
     */
    public static function commandSubject(string $command): string
    {
        $command = trim($command);

        return $command !== '' ? $command : 'VFS command';
    }
}
