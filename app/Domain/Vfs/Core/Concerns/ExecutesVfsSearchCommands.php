<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsArgs;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * grep / rg / zgrep.
 *
 * One per-file engine (grepContent) serves stdin, explicit files, and
 * recursive search, so every flag behaves the same in all three modes:
 * -i -v -w -x -F -E -o -n -c -l -L -q -s -m -A -B -C -H -h -I, multiple -e,
 * grep --include/--exclude, rg -g/--glob, -t/--type, --hidden, --files.
 *
 * Recursion walks readable files with walkFiles (maxFiles/maxDepth) and
 * matches on FULL content. When the walk is cut by its budget, the indexed
 * candidates of OpenCompanyVfs::searchWithMetadata are added (recall beyond
 * the walk), then the result ends with a truncation line.
 *
 * Exit status: 0 a line matched (for -L: a file was listed), 1 none, 2 an
 * operand failed (unless -q found a match). Every operand failing raises
 * that error as the envelope; partial failures go to stderr (silenced by -s).
 *
 * Display cap: maxMatches output lines when the output goes straight to the
 * agent (with a truncation line); never in a pipe, so `grep x f | wc -l`
 * counts every match.
 */
trait ExecutesVfsSearchCommands
{
    /** @var array<string, list<string>> rg --type names => extensions */
    private array $searchTypes = [
        'md' => ['md', 'markdown'], 'markdown' => ['md', 'markdown'], 'txt' => ['txt', 'text'], 'json' => ['json'],
        'ndjson' => ['ndjson', 'jsonl'], 'csv' => ['csv', 'tsv'], 'yaml' => ['yaml', 'yml'], 'html' => ['html', 'htm'], 'xml' => ['xml'],
    ];

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function grep(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin, string $command): array
    {
        $args = $this->args($command, $words);
        $rg = $command === 'rg';
        $maxDepth = $args->int('--max-depth');
        if ($maxDepth !== null) {
            $budget = $budget->with(['maxDepth' => $maxDepth]);
        }

        if ($rg && $args->has('--files')) {
            $walk = $this->walkFiles($agent, $this->operandPaths($args, $cwd) ?: [$cwd], $budget, $this->searchFileFilter($args, true), $args->has('--hidden'));
            $lines = $walk['files'];
            if ($walk['truncated_files'] || ($walk['truncated_depth'] && $maxDepth === null)) {
                $this->appendSearchTruncation($lines, "searched {$budget->maxFiles} files", 'maxFiles/maxDepth');
            }

            return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'read', 'exit_code' => $walk['files'] === [] ? 1 : 0, 'truncated' => $walk['truncated']];
        }

        $patterns = $args->values('-e');
        $operandOffset = 0;
        if ($patterns === []) {
            if ($args->operands === []) {
                throw VfsError::invalid("{$command} requires a pattern.", ['hint' => "{$command} PATTERN [PATH ...], or {$command} -e PATTERN"]);
            }
            $patterns = [$args->operands[0]];
            $operandOffset = 1;
        }
        $regex = $args->has('F') ? false : ($rg || $args->has('E'));
        $matcher = $this->grepMatcher($patterns, $regex, $args->has('i'), $args->has('w'), $args->has('x'), $command);
        $options = [
            'invert' => $args->has('v'),
            'only' => $args->has('o'),
            'number' => $args->has('n'),
            'count' => $args->has('c'),
            'list' => $args->has('l'),
            'list_missing' => $args->has('L'),
            'quiet' => $args->has('q'),
            'max_count' => $args->int(['-m']),
            'before' => $args->int('-B') ?? $args->int('-C') ?? 0,
            'after' => $args->int('-A') ?? $args->int('-C') ?? 0,
        ];

        $operands = $this->operandPaths($args, $cwd, $operandOffset);
        // rg recurses by default but, like ripgrep, reads piped stdin when no
        // path is given; grep -r without a path searches the cwd (GNU).
        $recursive = ($rg && ! ($operands === [] && $stdin !== null)) || $args->has('r', 'R');
        $displayCap = $this->displayingStdout && ! $options['quiet'] ? $budget->maxMatches : null;

        // Sources: stdin, explicit files, or a recursive walk.
        $sources = [];
        $errors = [];
        $walkTruncated = false;
        $walkedDirectory = false;
        if ($operands === [] && ! $recursive) {
            $sources[] = ['label' => null, 'content' => $stdin ?? ''];
        } else {
            $roots = $operands ?: [$cwd];
            if ($recursive) {
                $filter = $this->searchFileFilter($args, $rg);
                $walkable = [];
                foreach ($roots as $root) {
                    if ($root === '-') {
                        $sources[] = ['label' => '(standard input)', 'content' => $stdin ?? ''];

                        continue;
                    }
                    $walkable[] = $root;
                }
                try {
                    $walk = $walkable === [] ? ['files' => [], 'canonical' => [], 'truncated_files' => false, 'truncated_depth' => false, 'directories' => []] : $this->walkFiles($agent, $walkable, $budget, $filter, ! $rg || $args->has('--hidden'));
                } catch (VfsError $e) {
                    if (count($walkable) === 1 && $sources === []) {
                        throw $e;
                    }
                    $errors[] = $e;
                    $walk = ['files' => [], 'canonical' => [], 'truncated_files' => false, 'truncated_depth' => false, 'directories' => []];
                }
                $files = $walk['files'];
                $walkedDirectory = $walk['directories'] !== [];
                // An explicit --max-depth is a request, not a budget cut.
                $walkTruncated = $walk['truncated_files'] || ($walk['truncated_depth'] && $maxDepth === null);
                if ($walkTruncated && ! $options['invert'] && ! $options['list_missing']) {
                    $files = $this->mergeIndexedCandidates($agent, $files, $patterns, $regex, $args, $walkable, $budget, $filter);
                }
                foreach ($files as $file) {
                    // Stable canonical paths in search output (the facade
                    // search contract, e.g. /docs/by-id/<id>).
                    $label = $walk['canonical'][$file] ?? $file;
                    $sources[] = ['label' => $label, 'path' => $file];
                }
            } else {
                foreach ($roots as $root) {
                    $sources[] = $root === '-' ? ['label' => '(standard input)', 'content' => $stdin ?? ''] : ['label' => $root, 'path' => $root];
                }
            }
        }

        // GNU/ripgrep: names are shown for several sources or a walked folder.
        $withFilename = $args->has('H') || (! $args->has('h') && ($walkedDirectory || count($sources) > 1));
        $lines = [];
        $matchedAny = false;
        $shownLines = 0;
        $displayTruncated = false;
        $failedOperands = 0;
        foreach ($sources as $source) {
            if (array_key_exists('content', $source)) {
                $content = $source['content'];
            } else {
                try {
                    $content = $this->readFull($agent, $source['path']);
                } catch (VfsError $e) {
                    if (! $recursive && $e->errorCode === 'not_readable') {
                        $e = new VfsError('not_readable', "{$command}: {$source['path']}: Is a directory", ['path' => $source['path'], 'hint' => "Use {$command} -r PATTERN {$source['path']} to search inside it."]);
                    }
                    if ($recursive && $e->errorCode === 'not_readable') {
                        continue;
                    }
                    $errors[] = $e;
                    $failedOperands++;

                    continue;
                }
            }
            if ($args->has('I') && (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0"))) {
                continue;
            }
            $result = $this->grepContent($content, $matcher, $options, $withFilename ? $source['label'] : null);
            $matchedAny = $matchedAny || $result['matched'];
            if ($options['quiet']) {
                if ($matchedAny) {
                    break;
                }

                continue;
            }
            if ($options['list']) {
                if ($result['matched']) {
                    $lines[] = $source['label'] ?? '(standard input)';
                }
            } elseif ($options['list_missing']) {
                if (! $result['matched']) {
                    $lines[] = $source['label'] ?? '(standard input)';
                }
            } elseif ($options['count']) {
                $lines[] = ($withFilename && $source['label'] !== null ? $source['label'].':' : '').$result['count'];
            } else {
                foreach ($result['lines'] as $line) {
                    if ($displayCap !== null && $shownLines >= $displayCap) {
                        $displayTruncated = true;

                        break 2;
                    }
                    $lines[] = $line;
                    $shownLines++;
                }
            }
        }

        if ($errors !== [] && $failedOperands === count($sources) && $failedOperands > 0 && ! $recursive) {
            throw $errors[0];
        }
        if ($displayTruncated) {
            $this->appendSearchTruncation($lines, "showed {$shownLines} of ≥".($shownLines + 1).' matching lines', 'maxMatches');
        }
        if ($walkTruncated) {
            $this->appendSearchTruncation($lines, "searched {$budget->maxFiles} files without finishing the walk", 'maxFiles/maxDepth');
        }

        $status = $options['list_missing'] ? ($lines === [] ? 1 : 0) : ($matchedAny ? 0 : 1);
        if ($errors !== [] && ! ($options['quiet'] && $matchedAny)) {
            $status = 2;
        }

        return [
            'stdout' => $options['quiet'] ? '' : $this->linesOut($lines),
            'stderr' => $args->has('s') ? '' : implode("\n", array_map(fn (VfsError $e): string => $e->getMessage(), $errors)),
            'cwd' => $cwd,
            'class' => 'read',
            'exit_code' => $status,
            'count' => $shownLines,
            'truncated' => $displayTruncated || $walkTruncated,
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendSearchTruncation(array &$lines, string $what, string $budgetName): void
    {
        $line = "[truncated: {$what} — raise {$budgetName} or narrow the path]";
        if ($this->displayingStdout) {
            $lines[] = $line;
        } else {
            $this->noteTruncation($what);
        }
    }

    /**
     * Combined PCRE for all patterns with -F/-E/-w/-x/-i semantics.
     *
     * @param  list<string>  $patterns
     */
    private function grepMatcher(array $patterns, bool $regex, bool $ignoreCase, bool $word, bool $line, string $command): string
    {
        // Escape the ~ delimiter exactly once: preg_quote does it for literals.
        $bodies = array_map(fn (string $pattern): string => $regex ? str_replace('~', '\~', $pattern) : preg_quote($pattern, '~'), $patterns);
        $body = count($bodies) === 1 ? $bodies[0] : '(?:'.implode(')|(?:', $bodies).')';
        if ($word) {
            $body = '(?<![\p{L}\p{N}_])(?:'.$body.')(?![\p{L}\p{N}_])';
        }
        if ($line) {
            $body = '^(?:'.$body.')$';
        }
        foreach (['u', ''] as $unicode) {
            $expression = '~'.$body.'~'.$unicode.($ignoreCase ? 'i' : '');
            if (@preg_match($expression, '') !== false) {
                return $expression;
            }
        }

        throw VfsError::invalid("{$command}: invalid regular expression.", [
            'patterns' => $patterns,
            'hint' => $command === 'grep' ? 'grep without -E matches literally; with -E the pattern is a PCRE-style regex.' : 'Patterns are PCRE-style regexes; pass -F for a literal match.',
        ]);
    }

    /**
     * Match one text. Returns formatted output lines (with path/line/context
     * prefixes and "--" group separators), the matching-line count, and
     * whether anything matched.
     *
     * @param  array{invert: bool, only: bool, number: bool, count: bool, list: bool, list_missing: bool, quiet: bool, max_count: int|null, before: int, after: int}  $options
     * @return array{lines: list<string>, count: int, matched: bool}
     */
    private function grepContent(string $content, string $matcher, array $options, ?string $label): array
    {
        $lines = $this->splitLines($content);
        $count = 0;
        $selected = [];
        foreach ($lines as $index => $line) {
            $hit = preg_match($matcher, $line);
            if ($hit === false) {
                $hit = @preg_match(preg_replace('/u([a-z]*)$/', '$1', $matcher) ?? $matcher, $line);
            }
            $hit = $hit === 1;
            if ($options['invert']) {
                $hit = ! $hit;
            }
            if (! $hit) {
                continue;
            }
            $selected[] = $index;
            $count++;
            if ($options['max_count'] !== null && $count >= $options['max_count']) {
                break;
            }
        }
        if ($options['max_count'] === 0) {
            $selected = [];
            $count = 0;
        }
        $matched = $count > 0;
        if ($options['count'] || $options['list'] || $options['list_missing'] || $options['quiet']) {
            return ['lines' => [], 'count' => $count, 'matched' => $matched];
        }

        $prefix = fn (int $index, string $separator): string => ($label !== null ? $label.$separator : '').($options['number'] ? ($index + 1).$separator : '');
        $out = [];
        if ($options['only'] && ! $options['invert']) {
            foreach ($selected as $index) {
                preg_match_all($matcher, $lines[$index], $all);
                foreach ($all[0] ?? [] as $piece) {
                    if ($piece !== '') {
                        $out[] = $prefix($index, ':').$piece;
                    }
                }
            }

            return ['lines' => $out, 'count' => $count, 'matched' => $matched];
        }

        $isSelected = array_fill_keys($selected, true);
        $shown = [];
        foreach ($selected as $index) {
            for ($i = max(0, $index - $options['before']); $i <= min(count($lines) - 1, $index + $options['after']); $i++) {
                $shown[$i] = true;
            }
        }
        ksort($shown);
        $previous = null;
        $context = $options['before'] > 0 || $options['after'] > 0;
        foreach (array_keys($shown) as $index) {
            if ($context && $previous !== null && $index > $previous + 1) {
                $out[] = '--';
            }
            $out[] = $prefix($index, isset($isSelected[$index]) ? ':' : '-').$lines[$index];
            $previous = $index;
        }

        return ['lines' => $out, 'count' => $count, 'matched' => $matched];
    }

    /**
     * File filter for recursive search: grep --include/--exclude, rg
     * -g/--glob (! = exclude) and -t/--type.
     *
     * @return (callable(string, string): bool)|null
     */
    private function searchFileFilter(VfsArgs $args, bool $rg): ?callable
    {
        $includes = $args->values('--include');
        $excludes = $args->values('--exclude');
        foreach ($args->values(['-g', '--glob']) as $glob) {
            if (str_starts_with($glob, '!')) {
                $excludes[] = substr($glob, 1);
            } else {
                $includes[] = $glob;
            }
        }
        $types = [];
        foreach ($args->values(['-t', '--type']) as $type) {
            $extensions = $this->searchTypes[strtolower($type)] ?? throw VfsError::invalid("rg: unknown file type \"{$type}\".", ['supported_types' => array_keys($this->searchTypes)]);
            array_push($types, ...$extensions);
        }
        if ($includes === [] && $excludes === [] && $types === []) {
            return null;
        }

        return function (string $path, string $name) use ($includes, $excludes, $types): bool {
            $subject = fn (string $glob): string => str_contains($glob, '/') ? $path : $name;
            foreach ($excludes as $glob) {
                if (fnmatch($glob, $subject($glob))) {
                    return false;
                }
            }
            if ($includes !== []) {
                $included = false;
                foreach ($includes as $glob) {
                    if (fnmatch($glob, $subject($glob)) || (str_ends_with($glob, '.md') && str_starts_with($path, '/docs/') && ! str_contains($name, '.'))) {
                        $included = true;

                        break;
                    }
                }
                if (! $included) {
                    return false;
                }
            }
            if ($types !== []) {
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                // Docs are markdown records without an extension.
                $isDoc = $extension === '' && str_starts_with($path, '/docs/');

                return in_array($extension, $types, true) || ($isDoc && in_array('md', $types, true));
            }

            return true;
        };
    }

    /**
     * When the walk hit its budget, add files the content index knows match
     * (OpenCompanyVfs::searchWithMetadata re-authorizes every path).
     *
     * @param  list<string>  $files
     * @param  list<string>  $patterns
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function mergeIndexedCandidates(User $agent, array $files, array $patterns, bool $regex, VfsArgs $args, array $roots, VfsBudget $budget, ?callable $filter): array
    {
        if (count($patterns) !== 1) {
            return $files;
        }
        try {
            $search = $this->vfs->searchWithMetadata($agent, $patterns[0], $roots, $budget, $regex, $args->has('i'));
        } catch (VfsError) {
            return $files;
        }
        $known = array_fill_keys($files, true);
        foreach ($search['matches'] as $match) {
            $path = $match['path'];
            if ($path === '' || isset($known[$path]) || ($filter !== null && ! $filter($path, basename($path)))) {
                continue;
            }
            $known[$path] = true;
            $files[] = $path;
        }

        return $files;
    }
}
