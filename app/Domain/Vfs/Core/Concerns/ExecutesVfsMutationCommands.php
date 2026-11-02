<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPath;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Write and destructive shell commands (mkdir, touch, cp, mv, rm, mktemp,
 * truncate, tee) plus the shared write guards.
 *
 * Invariant: every method that mutates calls assertWriteCommandAllowed
 * (vfs_exec + vfs_write, subject = the whole command line, so one approval
 * covers the line and replay matches) BEFORE touching OpenCompanyVfs, and
 * then passes permissionAlreadyChecked only to the facade calls that would
 * otherwise re-check vfs_write.
 *
 * Multi-operand commands act on every operand (GNU behavior). They stop at
 * the first failure that is not a plain per-path error and report the paths
 * that were already changed, so a partial run is never silent.
 *
 * Output (VFS deviation from GNU, which is silent): one compact status line
 * per changed path — "created PATH", "copied SRC -> DEST", "moved SRC ->
 * DEST", "deleted PATH", "updated PATH" — so an agent sees exactly what the
 * line changed without a follow-up ls. -v prints the same lines.
 *
 * Governance note for later phases: `rm` routes to OpenCompanyVfs::remove,
 * which today hard-deletes the workspace file (owned by the /files mount);
 * "delete means trash" must be implemented there, not here.
 */
trait ExecutesVfsMutationCommands
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function mkdir(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('mkdir', $words);
        $paths = $this->operandPaths($args, $cwd);
        if ($paths === []) {
            throw VfsError::invalid('mkdir requires at least one path.');
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $parents = $args->has('p');
        $lines = [];

        $this->eachOperand($paths, function (string $path) use ($agent, $parents, &$lines): void {
            $existing = $this->statOrNull($agent, $path);
            if ($existing !== null) {
                if ($parents && ($existing['type'] ?? null) === 'directory') {
                    return;
                }
                throw VfsError::invalid("mkdir: cannot create directory '{$path}': File exists", ['path' => $path, 'hint' => 'Use mkdir -p to accept existing folders.']);
            }
            if (! $parents) {
                $parent = dirname($path);
                $parentStat = $parent === '/' ? ['type' => 'directory'] : $this->statOrNull($agent, $parent);
                if ($parentStat === null) {
                    throw VfsError::notFound($parent);
                }
            }
            $result = $this->vfs->makeDirectory($agent, $path);
            if (($result['status'] ?? null) === 'created') {
                $lines[] = "created {$result['path']}";
            }
        });

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function touch(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('touch', $words);
        $paths = $this->operandPaths($args, $cwd);
        if ($paths === []) {
            throw VfsError::invalid('touch requires at least one path.');
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $lines = [];
        $this->eachOperand($paths, function (string $path) use ($agent, $args, &$lines): void {
            if ($args->has('c') && $this->statOrNull($agent, $path) === null) {
                return;
            }
            $result = $this->vfs->touch($agent, $path);
            $lines[] = ($result['status'] ?? 'touched').' '.($result['path'] ?? $path);
        });

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * cp SRC DEST, cp SRC... DIR, cp -r DIR DEST. An existing destination
     * file is overwritten with a versioned write (GNU default); -n keeps it.
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function copy(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('cp', $words);
        $paths = $this->operandPaths($args, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('cp requires source and destination paths.', ['hint' => 'cp SRC DEST or cp SRC... DIR/']);
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $destination = array_pop($paths);
        $intoDirectory = $this->destinationIsDirectory($agent, $destination, count($paths) > 1, 'cp');
        $recursive = $args->has('r', 'R', 'a');
        $lines = [];

        $this->eachOperand($paths, function (string $source) use ($agent, $destination, $intoDirectory, $recursive, $args, &$lines): void {
            $target = $intoDirectory ? rtrim($destination, '/').'/'.basename($source) : $destination;
            $stat = $this->vfs->stat($agent, $source);
            if (($stat['type'] ?? null) === 'directory') {
                if (! $recursive) {
                    throw VfsError::invalid("cp: -r not specified; omitting directory '{$source}'", ['path' => $source, 'hint' => 'Use cp -r to copy folders.']);
                }
                $this->copyTree($agent, $source, $target, $args->has('n'), $lines);

                return;
            }
            $this->copyFile($agent, $source, $target, $args->has('n'), $lines);
        });

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $lines
     */
    private function copyFile(User $agent, string $source, string $target, bool $noClobber, array &$lines): void
    {
        if (VfsPath::normalize($source) === VfsPath::normalize($target)) {
            throw VfsError::invalid("cp: '{$source}' and '{$target}' are the same file", ['path' => $source]);
        }
        $existing = $this->statOrNull($agent, $target);
        if ($existing !== null) {
            if (($existing['type'] ?? null) === 'directory') {
                throw VfsError::invalid("cp: cannot overwrite directory '{$target}' with a file", ['path' => $target]);
            }
            if ($noClobber) {
                return;
            }
            $this->vfs->write($agent, $target, $this->readFull($agent, $source), 'overwrite', permissionAlreadyChecked: true);
        } else {
            $this->vfs->copy($agent, $source, $target);
        }
        $lines[] = "copied {$source} -> {$target}";
    }

    /**
     * Recursive folder copy bounded by maxFiles/maxDepth (walkFiles).
     *
     * @param  list<string>  $lines
     */
    private function copyTree(User $agent, string $source, string $target, bool $noClobber, array &$lines): void
    {
        if (str_starts_with(VfsPath::normalize($target).'/', VfsPath::normalize($source).'/')) {
            throw VfsError::invalid("cp: cannot copy a directory, '{$source}', into itself, '{$target}'", ['path' => $source]);
        }
        $walk = $this->walkFiles($agent, [$source], $this->currentBudget ?? new \App\Domain\Vfs\Core\VfsBudget);
        if ($walk['truncated']) {
            throw VfsError::invalid("cp -r: {$source} has more files or levels than the VFS budget allows (maxFiles/maxDepth); nothing was copied.", [
                'path' => $source,
                'hint' => 'Raise maxFiles/maxDepth or copy sub-folders separately.',
            ]);
        }
        $this->vfs->makeDirectory($agent, $target);
        foreach ($walk['directories'] as $directory) {
            $relative = ltrim(substr($directory, strlen(rtrim($source, '/'))), '/');
            if ($relative !== '') {
                $this->vfs->makeDirectory($agent, rtrim($target, '/').'/'.$relative);
            }
        }
        foreach ($walk['files'] as $file) {
            $relative = ltrim(substr($file, strlen(rtrim($source, '/'))), '/');
            $this->copyFile($agent, $file, rtrim($target, '/').'/'.$relative, $noClobber, $lines);
        }
    }

    /**
     * mv SRC DEST or mv SRC... DIR. An existing destination file is only
     * replaced with -f (VFS deviation: replacing destroys the old file).
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function move(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('mv', $words);
        $paths = $this->operandPaths($args, $cwd);
        if (count($paths) < 2) {
            throw VfsError::invalid('mv requires source and destination paths.', ['hint' => 'mv SRC DEST or mv SRC... DIR/']);
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $destination = array_pop($paths);
        $intoDirectory = $this->destinationIsDirectory($agent, $destination, count($paths) > 1, 'mv');
        $lines = [];

        $this->eachOperand($paths, function (string $source) use ($agent, $destination, $intoDirectory, $args, &$lines): void {
            $target = $intoDirectory ? rtrim($destination, '/').'/'.basename($source) : $destination;
            if (VfsPath::normalize($source) !== VfsPath::normalize($target)) {
                $existing = $this->statOrNull($agent, $target);
                if ($existing !== null) {
                    if ($args->has('n')) {
                        return;
                    }
                    if (! $args->has('f')) {
                        throw VfsError::invalid("mv: '{$target}' already exists", ['path' => $target, 'hint' => 'Use mv -f to replace it, or -n to skip existing files.']);
                    }
                    if (($existing['type'] ?? null) === 'directory') {
                        throw VfsError::invalid("mv: cannot overwrite directory '{$target}'", ['path' => $target]);
                    }
                    $this->vfs->remove($agent, $target);
                }
            }
            $result = $this->vfs->move($agent, $source, $target);
            $lines[] = ($result['status'] ?? null) === 'unchanged'
                ? "unchanged {$source}"
                : "moved {$source} -> ".($result['path'] ?? $target);
        });

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function remove(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('rm', $words);
        $paths = $this->operandPaths($args, $cwd);
        if ($paths === []) {
            if ($args->has('f')) {
                return ['stdout' => '', 'cwd' => $cwd, 'class' => 'destructive'];
            }
            throw VfsError::invalid('rm requires at least one path.');
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $lines = [];

        $this->eachOperand($paths, function (string $path) use ($agent, $args, &$lines): void {
            $stat = $this->statOrNull($agent, $path);
            if ($stat === null) {
                if ($args->has('f')) {
                    return;
                }
                throw VfsError::notFound($path);
            }
            $isDirectory = ($stat['type'] ?? null) === 'directory';
            if ($isDirectory && ! $args->has('r', 'R')) {
                if (! $args->has('d')) {
                    throw VfsError::invalid("rm: cannot remove '{$path}': Is a directory", ['path' => $path, 'hint' => 'Use rm -r for folders (rmdir / rm -d for empty folders).']);
                }
                if ($this->vfs->listBounded($agent, $path, new \App\Domain\Vfs\Core\VfsBudget(maxEntries: 1)) !== []) {
                    throw VfsError::invalid("rm: cannot remove '{$path}': Directory not empty", ['path' => $path, 'hint' => 'Use rm -r to remove a folder and its contents.']);
                }
            }
            // Delete means trash (governance model): report the backend's own
            // status ("trashed") rather than claiming a hard delete.
            $result = $this->vfs->remove($agent, $path);
            $lines[] = (is_string($result['status'] ?? null) ? $result['status'] : 'trashed').' '.$path;
        });

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'destructive'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function mktemp(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('mktemp', $words);
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $directory = $args->value('-p');
        $directory = $directory === null ? '/files/tmp' : $this->path($directory, $cwd);
        if (! str_starts_with($directory, '/files')) {
            throw VfsError::notWritable($directory);
        }
        $template = $args->operands[0] ?? 'tmp.XXXXXXXXXX';
        if (! str_contains($template, 'XXX')) {
            $template .= '.XXXXXXXX';
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $name = preg_replace_callback('/X{3,}/', fn (array $matches): string => Str::random(strlen($matches[0])), basename($template)) ?? $template;
            $path = rtrim($directory, '/').'/'.$name;
            if ($this->statOrNull($agent, $path) !== null) {
                continue;
            }
            $args->has('d')
                ? $this->vfs->makeDirectory($agent, $path)
                : $this->vfs->write($agent, $path, '', 'create', permissionAlreadyChecked: true);

            return ['stdout' => $path."\n", 'cwd' => $cwd, 'class' => 'write'];
        }

        throw VfsError::invalid('mktemp could not allocate a unique path.');
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function truncate(User $agent, array $words, string $cwd): array
    {
        $args = $this->args('truncate', $words);
        $size = $args->value('-s');
        if ($size === null) {
            throw VfsError::invalid('truncate requires -s 0 or --size=0.');
        }
        if (preg_match('/^0+$/', $size) !== 1) {
            throw VfsError::unsupported('VFS truncate supports only size 0.', ['suggestion' => 'Use head -c N file > /files/new to keep a prefix.']);
        }
        $paths = $this->operandPaths($args, $cwd);
        if ($paths === []) {
            throw VfsError::invalid('truncate requires a path.');
        }
        $this->assertWriteCommandAllowed($agent, 'vfs_write');
        $lines = [];
        $this->eachOperand($paths, function (string $path) use ($agent, &$lines): void {
            $result = $this->vfs->truncate($agent, $path);
            $lines[] = ($result['status'] ?? 'updated').' '.$path;
        });

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'write'];
    }

    /**
     * tee: copy stdin to stdout and to every operand file.
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function tee(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('tee', $words);
        $paths = $this->operandPaths($args, $cwd);
        $content = $stdin ?? '';
        if ($paths !== []) {
            $this->assertWriteCommandAllowed($agent, 'vfs_write');
        }
        $this->eachOperand($paths, function (string $path) use ($agent, $content, $args): void {
            $this->writeRedirectTarget($agent, $path, $content, $args->has('a') ? 'append' : 'overwrite');
        });

        return ['stdout' => $content, 'cwd' => $cwd, 'class' => 'write', 'written' => $paths];
    }

    /**
     * Run $operation for each path; per-path failures (not approvals or
     * unsupported operations, which abort) are collected so every operand is
     * attempted, then the first failure is raised with the full picture.
     *
     * @param  list<string>  $paths
     */
    private function eachOperand(array $paths, callable $operation): void
    {
        $failed = [];
        $completed = [];
        $first = null;
        foreach ($paths as $path) {
            try {
                $operation($path);
                $completed[] = $path;
            } catch (VfsError $e) {
                if (in_array($e->errorCode, ['approval_required', 'unsupported', 'conflict'], true) || count($paths) === 1) {
                    if ($completed !== [] && $e->errorCode !== 'approval_required') {
                        throw new VfsError($e->errorCode, $e->getMessage(), $e->context + ['completed_paths' => $completed]);
                    }
                    throw $e;
                }
                $first ??= $e;
                $failed[] = ['path' => $path, 'code' => $e->errorCode, 'message' => $e->getMessage()];
            }
        }
        if ($first !== null) {
            throw new VfsError($first->errorCode, $first->getMessage().(count($failed) > 1 ? ' (and '.(count($failed) - 1).' more failures)' : ''), $first->context + [
                'failed' => $failed,
                'completed_paths' => $completed,
            ]);
        }
    }

    /**
     * Whether DEST is (or must be) a directory for cp/mv.
     */
    private function destinationIsDirectory(User $agent, string $destination, bool $multipleSources, string $command): bool
    {
        $stat = $this->statOrNull($agent, $destination);
        $isDirectory = ($stat['type'] ?? null) === 'directory';
        if ($multipleSources && ! $isDirectory) {
            throw VfsError::invalid("{$command}: target '{$destination}' is not a directory", ['path' => $destination, 'hint' => "Create it first (mkdir -p {$destination}) when copying several sources."]);
        }

        return $isDirectory;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function statOrNull(User $agent, string $path): ?array
    {
        try {
            return $this->vfs->stat($agent, $path);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_found') {
                return null;
            }
            throw $e;
        }
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
     * Full current content and version of an append target, or ['', null]
     * when it does not exist.
     *
     * Invariant: append is read-modify-write, so this must be a content read
     * and the version must be passed to the write; a display read would
     * persist a truncation line, and an unversioned write could drop a
     * concurrent change.
     *
     * @return array{0: string, 1: string|null}
     */
    private function existingContentForAppend(User $agent, string $path): array
    {
        try {
            $current = $this->vfs->readContentWithVersion($agent, $path);

            return [$current['content'], $current['version']];
        } catch (VfsError $e) {
            if ($e->errorCode !== 'not_found') {
                throw $e;
            }
        }

        return ['', null];
    }

    /**
     * Refuse to persist command output that carries a display truncation line
     * produced by a budget-truncated read in this execution. Both conditions
     * are required so a file that merely documents the marker text can still
     * be copied when nothing was truncated.
     */
    private function assertNotPersistingTruncatedOutput(string $content, string $path): void
    {
        if (($this->currentBudget?->truncations() ?? 0) > 0 && VfsText::hasAnyTruncationLine($content)) {
            throw VfsError::invalid("Refusing to write truncated output to {$path}: an input read in this command was cut at maxBytes.", [
                'path' => $path,
                'hint' => 'Redirect the full content (cat FILE > DEST reads everything), or use cp to copy whole files.',
            ]);
        }
    }
}
