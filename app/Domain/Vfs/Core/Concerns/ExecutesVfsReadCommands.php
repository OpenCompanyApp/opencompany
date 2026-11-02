<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsText;
use App\Models\User;

/**
 * Read-only text commands: cat, head, tail, wc.
 *
 * Read modes: `cat` whose stdout goes straight to the agent makes a display
 * read (cut at maxBytes, ending with the stable "[truncated at N bytes of M
 * ...]" line; paged collections show one page and a cursor line). Every other
 * read here — cat into a pipe or redirect, head, tail, wc — is a full read, so
 * `tail` sees the real end of a 150 KB file and `wc -l` counts every line.
 *
 * Never mutates; all reads go through OpenCompanyVfs with normal authorization.
 */
trait ExecutesVfsReadCommands
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function cat(User $agent, array $words, string $cwd, \App\Domain\Vfs\Core\VfsBudget $budget, ?string $stdin): array
    {
        $args = $this->args('cat', $words);
        $paths = $this->operandPaths($args, $cwd);
        $truncated = false;
        $errors = [];
        if ($paths === []) {
            $content = $stdin ?? '';
        } else {
            $cursor = count($paths) === 1 && $this->displayingStdout ? $this->takeCursor() : null;
            $chunks = [];
            foreach ($paths as $path) {
                if ($path === '-') {
                    $chunks[] = (string) $stdin;

                    continue;
                }
                // GNU cat: a failing operand is reported and the rest still
                // print (exit 1); only an all-failing call is an error.
                try {
                    if ($this->displayingStdout) {
                        [$chunk, $wasTruncated] = $this->displayRead($agent, $path, $budget, $cursor);
                        $truncated = $truncated || $wasTruncated;
                        $chunks[] = $chunk;
                    } else {
                        $chunks[] = $this->readFull($agent, $path);
                    }
                } catch (VfsError $e) {
                    if (count($paths) === 1 || in_array($e->errorCode, ['approval_required', 'unsupported'], true)) {
                        throw $e;
                    }
                    $errors[] = $e;
                }
            }
            if ($chunks === [] && $errors !== []) {
                throw $errors[0];
            }
            $content = $this->joinFileContents($chunks);
        }

        if ($args->has('n')) {
            $lines = $this->splitLines($content);
            $content = $this->linesOut(array_map(fn (string $line, int $index): string => str_pad((string) ($index + 1), 6, ' ', STR_PAD_LEFT)."\t".$line, $lines, array_keys($lines)));
        }

        return [
            'stdout' => $content,
            'stderr' => implode("\n", array_map(fn (VfsError $e): string => $e->getMessage(), $errors)),
            'cwd' => $cwd,
            'class' => 'read',
            'truncated' => $truncated,
            'exit_code' => $errors === [] ? 0 : 1,
        ];
    }

    /**
     * One display read: a page of a collection (with cursor line) or a file
     * body cut at maxBytes (with the read truncation line).
     *
     * @return array{0: string, 1: bool}
     */
    private function displayRead(User $agent, string $path, \App\Domain\Vfs\Core\VfsBudget $budget, ?string $cursor): array
    {
        $before = $budget->truncations();
        try {
            $page = $this->vfs->readPage($agent, $path, $budget, $cursor);
        } catch (VfsError $e) {
            if ($e->errorCode === 'not_readable') {
                throw new VfsError('not_readable', "{$path}: Is a directory (or not readable as text).", $e->context + ['path' => $path, 'hint' => "Use ls {$path} to list it."]);
            }
            throw $e;
        }
        $content = $page->content;

        if ($page->nextCursor !== null) {
            $this->offerNextCursor($page->nextCursor);
            $separator = $content === '' || str_ends_with($content, "\n") ? '' : "\n";

            return [$content.$separator.VfsText::listingTruncationLine($page->seenBefore + $page->returned, 'records', $page->nextCursor)."\n", true];
        }
        if ($budget->truncations() > $before && str_ends_with($content, VfsText::TRUNCATION_SUFFIX)) {
            $shown = substr($content, 0, -strlen(VfsText::TRUNCATION_SUFFIX));
            $size = null;
            try {
                $stat = $this->vfs->stat($agent, $path);
                $size = isset($stat['size']) ? (int) $stat['size'] : (isset($stat['metadata']['size']) ? (int) $stat['metadata']['size'] : null);
            } catch (VfsError) {
                // Size is informational only.
            }
            $separator = str_ends_with($shown, "\n") ? '' : "\n";

            return [$shown.$separator.VfsText::readTruncationLine(strlen($shown), $size)."\n", true];
        }

        return [$content, false];
    }

    /**
     * head/tail with -n/-c, +N (tail from start), -N (head all but last N),
     * numeric shorthand, and ==> name <== headers for several files.
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function headTail(User $agent, array $words, string $cwd, ?string $stdin, bool $head): array
    {
        $command = $head ? 'head' : 'tail';
        $args = $this->args($command, $words);
        $unit = 'lines';
        $spec = '10';
        if ($args->numeric !== null) {
            preg_match('/^(\d+)([a-z]?)$/', $args->numeric, $m);
            $spec = $m[1];
            $unit = ($m[2] ?? '') === 'c' ? 'bytes' : 'lines';
        }
        if ($args->provided('-n')) {
            $spec = (string) $args->value('-n');
            $unit = 'lines';
        }
        if ($args->provided('-c')) {
            $spec = (string) $args->value('-c');
            $unit = 'bytes';
        }
        if (preg_match('/^([+-]?)(\d+)([kKmM]?)$/', $spec, $m) !== 1) {
            throw VfsError::invalid("{$command}: invalid number of {$unit}: \"{$spec}\".");
        }
        $count = (int) $m[2] * match (strtolower($m[3])) {
            'k' => 1024,
            'm' => 1024 * 1024,
            default => 1,
        };
        $sign = $m[1];

        $inputs = $this->inputsFrom($agent, $args, $cwd, $stdin);
        $headers = count($inputs) > 1 ? ! $args->has('q') : $args->has('v');
        $out = '';
        foreach ($inputs as $index => $input) {
            if ($headers) {
                $out .= ($index > 0 ? "\n" : '').'==> '.($input['name'] === '-' ? 'standard input' : $input['name'])." <==\n";
            }
            $out .= $unit === 'bytes'
                ? $this->sliceBytes($input['content'], $count, $sign, $head)
                : $this->sliceLines($input['content'], $count, $sign, $head);
        }

        return ['stdout' => $out, 'cwd' => $cwd, 'class' => 'read'];
    }

    private function sliceBytes(string $content, int $count, string $sign, bool $head): string
    {
        if ($head) {
            return $sign === '-' ? substr($content, 0, max(0, strlen($content) - $count)) : substr($content, 0, $count);
        }
        if ($sign === '+') {
            return substr($content, max(0, $count - 1));
        }

        return $count === 0 ? '' : substr($content, -$count);
    }

    /**
     * Byte-exact line slicing: kept lines keep their own terminators, so a
     * file without a final newline is not given one.
     */
    private function sliceLines(string $content, int $count, string $sign, bool $head): string
    {
        $lines = preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $total = count($lines);
        $slice = match (true) {
            $head && $sign === '-' => array_slice($lines, 0, max(0, $total - $count)),
            $head => array_slice($lines, 0, $count),
            $sign === '+' => array_slice($lines, max(0, $count - 1)),
            $count === 0 => [],
            default => array_slice($lines, -$count),
        };

        return implode('', $slice);
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function wc(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('wc', $words);
        $inputs = $this->inputsFrom($agent, $args, $cwd, $stdin);
        $selected = array_values(array_filter(['l', 'w', 'm', 'c', 'L'], fn (string $flag): bool => $args->has($flag)));
        if ($selected === []) {
            $selected = ['l', 'w', 'c'];
        }

        $rows = [];
        $totals = array_fill_keys($selected, 0);
        foreach ($inputs as $input) {
            $content = $input['content'];
            $counts = [];
            foreach ($selected as $flag) {
                $counts[$flag] = match ($flag) {
                    'l' => substr_count($content, "\n"),
                    'w' => preg_match_all('/\S+/u', $content) ?: preg_match_all('/\S+/', $content),
                    'm' => mb_check_encoding($content, 'UTF-8') ? mb_strlen($content, 'UTF-8') : strlen($content),
                    'c' => strlen($content),
                    'L' => max(array_map(fn (string $line): int => mb_strlen($line), $this->splitLines($content)) ?: [0]),
                };
                $totals[$flag] = $flag === 'L' ? max($totals[$flag], $counts[$flag]) : $totals[$flag] + $counts[$flag];
            }
            $rows[] = [$counts, $input['name'] === '-' ? null : $input['name']];
        }
        if (count($inputs) > 1) {
            $rows[] = [$totals, 'total'];
        }

        $single = count($rows) === 1 && count($selected) === 1;
        $width = $single ? 0 : max(array_map(fn (array $row): int => max(array_map(fn (int $n): int => strlen((string) $n), $row[0])), $rows));
        $lines = array_map(function (array $row) use ($width): string {
            $numbers = implode(' ', array_map(fn (int $n): string => str_pad((string) $n, $width, ' ', STR_PAD_LEFT), $row[0]));

            return $row[1] === null ? $numbers : $numbers.' '.$row[1];
        }, $rows);

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'read'];
    }
}
