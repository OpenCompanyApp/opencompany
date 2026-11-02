<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsArgs;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPath;
use App\Models\User;

/**
 * Shared argv, path, glob, and input helpers for VFS shell commands.
 *
 * Owns: spec-driven argument parsing for the running command, glob expansion
 * against the VFS (any segment, `**` within maxDepth, bounded listings),
 * full-content reads that drain paged collection files, the bounded file walk
 * used by recursive commands, and line splitting/joining conventions.
 *
 * Read-mode invariant: commands that process file content (filters, grep,
 * wc, checksums, diff, jq, sed, awk, head/tail, archives, appends) use
 * readFull(), which never truncates and fails loudly above the content
 * ceiling. Only `cat` whose output goes straight to the agent uses a display
 * read (see ExecutesVfsReadCommands). This is what keeps `cat big | wc -l`,
 * `tail big`, and `sha256sum big` truthful.
 */
trait ReadsVfsCommandInputs
{
    /** Bound on directories one glob expansion may list. */
    private int $globDirectoryBudget = 50;

    /**
     * Parse the running command's argv with its spec.
     *
     * @param  list<string>  $words
     */
    private function args(string $command, array $words): VfsArgs
    {
        $spec = VfsCommandSpecRegistry::get($command) ?? throw VfsError::unsupported("Unknown VFS command: {$command}. Run `help` to list commands.");

        return VfsArgs::parse($spec, $words, $this->argLiteral, $this->argGlob);
    }

    /**
     * Operand paths from index $from, normalized against cwd and glob-expanded.
     *
     * @return list<string>
     */
    private function operandPaths(VfsArgs $args, string $cwd, int $from = 0): array
    {
        $paths = [];
        foreach (array_slice($args->operands, $from, null, true) as $index => $operand) {
            if ($operand === '-') {
                $paths[] = '-';

                continue;
            }
            array_push($paths, ...$this->expandPathArg($operand, $cwd, $args->operandGlob[$index] ?? false));
        }

        return $paths;
    }

    private function path(string $path, string $cwd): string
    {
        return $this->vfs->normalizePath($path, $cwd);
    }

    /**
     * Expand one word into paths. Unquoted `*`, `?`, `[...]` may appear in any
     * segment; `**` matches any depth up to maxDepth. A pattern with no match
     * stays literal (bash without nullglob), so the command reports the
     * missing path itself.
     *
     * @return list<string>
     */
    private function expandPathArg(string $token, string $cwd, bool $allowGlob = true): array
    {
        $path = $this->path($token, $cwd);
        if (! $allowGlob || strpbrk($path, '*?[') === false) {
            return [$path];
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $part): bool => $part !== ''));
        $candidates = ['/'];
        $listed = 0;
        $maxDepth = $this->currentBudget->maxDepth ?? 3;
        $lastGlob = 0;
        foreach ($segments as $index => $segment) {
            if (strpbrk($segment, '*?[') !== false) {
                $lastGlob = $index;
            }
        }
        $literalTail = false;
        foreach ($segments as $index => $segment) {
            $more = $index < count($segments) - 1;
            $next = [];
            if (strpbrk($segment, '*?[') === false) {
                foreach ($candidates as $candidate) {
                    $next[] = rtrim($candidate, '/').'/'.$segment;
                }
                $candidates = $next;
                $literalTail = $literalTail || $index > $lastGlob;

                continue;
            }
            foreach ($candidates as $candidate) {
                if ($segment === '**') {
                    // ** = zero or more directories.
                    foreach ($this->globDirectories($candidate, $maxDepth, $listed) as $directory) {
                        $next[] = $directory;
                    }

                    continue;
                }
                foreach ($this->globList($candidate, $listed) as $entry) {
                    // A segment followed by more segments can only match folders.
                    if ($more && $entry->type !== 'directory') {
                        continue;
                    }
                    if (fnmatch($segment, $entry->name, FNM_PERIOD)) {
                        $prefix = rtrim($candidate, '/').'/';
                        $next[] = str_starts_with($entry->path, $prefix) ? $entry->path : $prefix.$entry->name;
                    }
                }
            }
            if ($next === []) {
                return [$path];
            }
            $candidates = array_values(array_unique($next));
        }

        if ($literalTail) {
            // Literal segments after the last glob were appended blindly;
            // keep only paths that exist (bash never returns phantom matches).
            $candidates = array_values(array_filter(array_slice($candidates, 0, 200), function (string $candidate): bool {
                try {
                    $this->vfs->stat($this->agentForPathExpansion(), $candidate);

                    return true;
                } catch (VfsError) {
                    return false;
                }
            }));
            if ($candidates === []) {
                return [$path];
            }
        }
        sort($candidates);

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<\App\Domain\Vfs\Core\VfsEntry>
     */
    private function globList(string $directory, int &$listed): array
    {
        if (++$listed > $this->globDirectoryBudget) {
            $this->noteTruncation('glob expansion listed '.$this->globDirectoryBudget.' directories and stopped; narrow the pattern.');

            return [];
        }
        try {
            return $this->vfs->listBounded($this->agentForPathExpansion(), $directory, new VfsBudget(maxEntries: 1_000, maxFiles: 2_000));
        } catch (VfsError $e) {
            if (in_array($e->errorCode, ['not_found', 'not_readable'], true)) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * $start plus every directory below it up to $maxDepth levels.
     *
     * @return list<string>
     */
    private function globDirectories(string $start, int $maxDepth, int &$listed): array
    {
        $out = [$start];
        $frontier = [$start];
        for ($depth = 0; $depth < $maxDepth && $frontier !== []; $depth++) {
            $next = [];
            foreach ($frontier as $directory) {
                foreach ($this->globList($directory, $listed) as $entry) {
                    if ($entry->type === 'directory') {
                        $child = rtrim($directory, '/').'/'.$entry->name;
                        $out[] = $child;
                        $next[] = $child;
                    }
                }
            }
            $frontier = $next;
        }

        return $out;
    }

    private function contentCeiling(): int
    {
        return max(1, (int) config('vfs.max_content_bytes', 20 * 1024 * 1024));
    }

    /**
     * Full content of one readable path. Generated paged collections
     * (rows.ndjson, channel day transcripts, members.json) are drained page by
     * page so a pipe sees every record, never just the first page.
     *
     * @throws VfsError invalid_request above the content ceiling; not_readable for directories
     */
    private function readFull(User $agent, string $path): string
    {
        $ceiling = $this->contentCeiling();
        $budget = new VfsBudget(maxEntries: 1_000);
        $budget->contentCeiling = $ceiling;
        try {
            $page = $this->vfs->readPage($agent, $path, $budget);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_readable') {
                throw new VfsError('not_readable', "{$path}: Is a directory (or not readable as text).", $e->context + ['path' => $path, 'hint' => "Use ls {$path} to list it, or grep -r / find to search inside it."]);
            }
            throw $e;
        }
        $content = $page->content;
        while ($page->nextCursor !== null) {
            if (strlen($content) > $ceiling) {
                throw VfsError::invalid("{$path} is larger than the VFS content limit ({$ceiling} bytes).", [
                    'path' => $path,
                    'max_content_bytes' => $ceiling,
                    'hint' => 'Read it page by page with cat and the cursor parameter.',
                ]);
            }
            $page = $this->vfs->readPage($agent, $path, $budget, $page->nextCursor);
            $content = $this->joinPages($path, $content, $page->content);
        }

        return $content;
    }

    /**
     * Join two pages of a generated collection into one valid document.
     */
    private function joinPages(string $path, string $left, string $right): string
    {
        if (str_ends_with($path, '.json')) {
            $a = json_decode($left, true);
            $b = json_decode($right, true);
            if (is_array($a) && is_array($b)) {
                if (array_is_list($a) && array_is_list($b)) {
                    return (string) json_encode([...$a, ...$b], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
                foreach ($b as $key => $value) {
                    $a[$key] = is_array($value) && array_is_list($value) && is_array($a[$key] ?? null) ? [...$a[$key], ...$value] : $value;
                }

                return (string) json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            throw VfsError::invalid("{$path} is paged and its pages cannot be combined.", ['path' => $path, 'hint' => 'Read it page by page with cat and the cursor parameter.']);
        }
        if ($left === '' || str_ends_with($left, "\n")) {
            return $left.$right;
        }

        return $left."\n".$right;
    }

    /**
     * Concatenated input for a filter: operand files from $from (`-` = stdin),
     * or stdin when there are no operands. A newline is inserted between files
     * that lack a trailing one.
     */
    private function inputFrom(User $agent, VfsArgs $args, string $cwd, ?string $stdin, int $from = 0): string
    {
        $parts = [];
        foreach ($this->inputsFrom($agent, $args, $cwd, $stdin, $from) as $input) {
            $parts[] = $input['content'];
        }

        return $this->joinFileContents($parts);
    }

    /**
     * Inputs as separate streams (awk FILENAME/FNR, sed -s, wc per file).
     *
     * @return list<array{name: string, content: string}>
     */
    private function inputsFrom(User $agent, VfsArgs $args, string $cwd, ?string $stdin, int $from = 0): array
    {
        $paths = $this->operandPaths($args, $cwd, $from);
        if ($paths === []) {
            return [['name' => '-', 'content' => $stdin ?? '']];
        }
        $inputs = [];
        foreach ($paths as $path) {
            $inputs[] = ['name' => $path, 'content' => $path === '-' ? (string) $stdin : $this->readFull($agent, $path)];
        }

        return $inputs;
    }

    /**
     * @param  list<string>  $contents
     */
    private function joinFileContents(array $contents): string
    {
        $out = '';
        foreach ($contents as $content) {
            if ($out !== '' && ! str_ends_with($out, "\n")) {
                $out .= "\n";
            }
            $out .= $content;
        }

        return $out;
    }

    /**
     * Split text into lines without a phantom empty last line.
     *
     * @return list<string>
     */
    private function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }
        $lines = explode("\n", str_replace("\r\n", "\n", $text));
        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * POSIX line output: every line terminated by a newline.
     *
     * @param  list<string>  $lines
     */
    private function linesOut(array $lines): string
    {
        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    /**
     * Bounded recursive walk returning readable file paths under the roots.
     * One listing per directory; stops at maxFiles files or maxDepth levels
     * and reports truncation instead of silently scanning less.
     *
     * @param  list<string>  $roots
     * @param  (callable(string, string): bool)|null  $include  receives (path, name) for files
     * @param  bool  $includeHidden  include entries whose name starts with "."
     * `files` are display paths under the roots (relative structure for cp -r,
     * tar, diff -r); `canonical` maps each to the object's canonical path,
     * which search output uses because it is stable (e.g. /docs/by-id/<id>).
     *
     * @return array{files: list<string>, canonical: array<string, string>, truncated: bool, truncated_files: bool, truncated_depth: bool, skipped: int, directories: list<string>}
     */
    private function walkFiles(User $agent, array $roots, VfsBudget $budget, ?callable $include = null, bool $includeHidden = true): array
    {
        $files = [];
        $canonical = [];
        $directories = [];
        $truncatedFiles = false;
        $truncatedDepth = false;
        $skipped = 0;
        $seen = [];
        $seenEntries = [];
        $queue = [];
        foreach ($roots as $root) {
            try {
                $stat = $this->vfs->stat($agent, $root);
            } catch (VfsError $e) {
                if (in_array($e->errorCode, ['not_found', 'permission_denied'], true)) {
                    throw $e;
                }
                $skipped++;

                continue;
            }
            if (($stat['type'] ?? 'directory') === 'directory') {
                $queue[] = [$root, 0];
            } elseif ($include === null || $include($root, basename($root))) {
                $files[] = $root;
            }
        }

        while ($queue !== []) {
            [$directory, $depth] = array_shift($queue);
            if (isset($seen[$directory])) {
                continue;
            }
            $seen[$directory] = true;
            // Entries of a directory at depth d sit at depth d + 1, so a
            // directory at the depth limit is not listed (find -maxdepth).
            if ($depth >= $budget->maxDepth) {
                $truncatedDepth = true;

                continue;
            }
            $directories[] = $directory;
            try {
                $entries = $this->vfs->listBounded($agent, $directory, new VfsBudget(maxEntries: 1_000, maxFiles: max($budget->maxFiles, 100)));
            } catch (VfsError) {
                $skipped++;

                continue;
            }
            foreach ($entries as $entry) {
                if (! $includeHidden && str_starts_with($entry->name, '.')) {
                    continue;
                }
                // Report display paths under the walked root (so relative
                // names work for cp -r, tar, diff -r) but dedupe by canonical
                // identity (a doc is reachable by slug and by id).
                $prefix = rtrim($directory, '/').'/';
                $path = str_starts_with($entry->path, $prefix) ? $entry->path : $prefix.$entry->name;
                $identity = $entry->canonicalPath ?? $path;
                if (isset($seenEntries[$identity])) {
                    continue;
                }
                $seenEntries[$identity] = true;
                if ($entry->type === 'directory') {
                    $queue[] = [$path, $depth + 1];

                    continue;
                }
                if ($include !== null && ! $include($path, $entry->name)) {
                    continue;
                }
                if (count($files) >= $budget->maxFiles) {
                    $truncatedFiles = true;

                    break 2;
                }
                $files[] = $path;
                $canonical[$path] = $identity;
            }
        }

        return [
            'files' => array_values(array_unique($files)),
            'canonical' => $canonical,
            'truncated' => $truncatedFiles || $truncatedDepth,
            'truncated_files' => $truncatedFiles,
            'truncated_depth' => $truncatedDepth,
            'skipped' => $skipped,
            'directories' => $directories,
        ];
    }

    /**
     * Record a budget truncation the agent must see: shown as a line in
     * stdout when the command's output is displayed, otherwise in stderr.
     */
    private function noteTruncation(string $message): void
    {
        $this->anyTruncated = true;
        $this->appendStderr("[truncated: {$message}]");
    }

    /**
     * Cursor supplied by the caller, handed to the first pageable command.
     */
    private function takeCursor(): ?string
    {
        $cursor = $this->pendingCursor;
        $this->pendingCursor = null;

        return $cursor;
    }

    private function offerNextCursor(?string $cursor): void
    {
        if ($cursor !== null && $this->nextCursor === null) {
            $this->nextCursor = $cursor;
        }
    }

    /**
     * Whether the path is the root of a domain mount (not /files), used by
     * commands that must route writes through governed domain APIs.
     */
    private function mountRoot(string $path): string
    {
        $normalized = VfsPath::normalize($path);

        return explode('/', ltrim($normalized, '/'))[0];
    }
}
