<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsArgs;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsLineDiff;
use App\Models\User;

/**
 * Comparison commands: diff (VfsLineDiff), cmp, comm, and checksums.
 *
 * All comparisons read full content (never a truncated display read), and
 * report differences through the exit status as GNU does: 0 equal, 1
 * different, 2 error. `diff -u` output is a valid unified diff with
 * `--- a/<path>` / `+++ b/<path>` headers.
 */
trait ExecutesVfsComparisonTransforms
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function diff(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $args = $this->args('diff', $words);
        $this->pendingDiffStdin = $stdin;
        $paths = $this->operandPaths($args, $cwd);
        if (count($paths) !== 2) {
            throw VfsError::invalid('diff requires exactly two paths.', ['hint' => 'diff [-u] OLD NEW, or diff -r DIR1 DIR2']);
        }
        [$left, $right] = $paths;
        $leftDirectory = $left !== '-' && ($this->statOrNull($agent, $left)['type'] ?? null) === 'directory';
        $rightDirectory = $right !== '-' && ($this->statOrNull($agent, $right)['type'] ?? null) === 'directory';

        if ($leftDirectory && $rightDirectory) {
            return $this->diffDirectories($agent, $args, $left, $right, $budget, $cwd);
        }
        if ($leftDirectory) {
            $left = rtrim($left, '/').'/'.basename($right);
        } elseif ($rightDirectory) {
            $right = rtrim($right, '/').'/'.basename($left);
        }

        $out = $this->diffPair($agent, $args, $left, $right, false);

        return ['stdout' => $out, 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => $this->lastDiffEqual ? 0 : 1];
    }

    private bool $lastDiffEqual = true;

    /**
     * Diff two files (or `-` for stdin) and format per flags. Sets
     * lastDiffEqual.
     */
    private function diffPair(User $agent, VfsArgs $args, string $left, string $right, bool $header): string
    {
        $read = function (string $path) use ($agent, $args): string {
            if ($path === '-') {
                return (string) ($this->pendingDiffStdin ?? '');
            }
            if ($args->has('N') && $this->statOrNull($agent, $path) === null) {
                return '';
            }

            return $this->readFull($agent, $path);
        };
        $diff = new VfsLineDiff($read($left), $read($right), [
            'ignore_case' => $args->has('i'),
            'ignore_all_space' => $args->has('w'),
            'ignore_space_change' => $args->has('b'),
            'ignore_blank_lines' => $args->has('B'),
        ]);
        $this->lastDiffEqual = $diff->identical();
        if ($this->lastDiffEqual) {
            return $args->has('s') ? "Files {$left} and {$right} are identical\n" : '';
        }
        if ($args->has('q')) {
            return "Files {$left} and {$right} differ\n";
        }
        $unified = $args->has('u') || $args->provided('-U');
        $prefix = $header ? 'diff '.($unified ? '-u ' : '')."{$left} {$right}\n" : '';
        if ($unified) {
            $context = $args->int('-U') ?? 3;

            return $prefix.$diff->unified('a'.($left === '-' ? '/-' : $left), 'b'.($right === '-' ? '/-' : $right), $context);
        }

        return $prefix.$diff->normal();
    }

    private ?string $pendingDiffStdin = null;

    /**
     * Compare two directory trees (one level without -r) within budgets.
     *
     * @return array<string, mixed>
     */
    private function diffDirectories(User $agent, VfsArgs $args, string $left, string $right, VfsBudget $budget, string $cwd): array
    {
        $recursive = $args->has('r');
        $scan = function (string $root) use ($agent, $budget, $recursive): array {
            if ($recursive) {
                $walk = $this->walkFiles($agent, [$root], $budget);
                if ($walk['truncated']) {
                    $this->noteTruncation("diff -r compared only part of {$root} (maxFiles/maxDepth).");
                }

                return [array_map(fn (string $file): string => ltrim(substr($file, strlen(rtrim($root, '/'))), '/'), $walk['files']), []];
            }
            $files = [];
            $directories = [];
            foreach ($this->vfs->listBounded($agent, $root, $budget->with(['maxEntries' => 1_000])) as $entry) {
                $entry->type === 'directory' ? $directories[] = $entry->name : $files[] = $entry->name;
            }

            return [$files, $directories];
        };
        [$leftFiles, $leftDirs] = $scan($left);
        [$rightFiles, $rightDirs] = $scan($right);
        $all = array_values(array_unique([...$leftFiles, ...$rightFiles]));
        sort($all);
        $out = '';
        $different = false;
        foreach ($all as $relative) {
            $inLeft = in_array($relative, $leftFiles, true);
            $inRight = in_array($relative, $rightFiles, true);
            $leftPath = rtrim($left, '/').'/'.$relative;
            $rightPath = rtrim($right, '/').'/'.$relative;
            if ((! $inLeft || ! $inRight) && ! $args->has('N')) {
                $different = true;
                $out .= 'Only in '.dirname($inLeft ? $leftPath : $rightPath).': '.basename($relative)."\n";

                continue;
            }
            $out .= $this->diffPair($agent, $args, $leftPath, $rightPath, true);
            $different = $different || ! $this->lastDiffEqual;
        }
        foreach (array_values(array_unique([...$leftDirs, ...$rightDirs])) as $directory) {
            $inLeft = in_array($directory, $leftDirs, true);
            $inRight = in_array($directory, $rightDirs, true);
            if ($inLeft && $inRight) {
                $out .= "Common subdirectories: {$left}/{$directory} and {$right}/{$directory}\n";
            } else {
                $different = true;
                $out .= 'Only in '.($inLeft ? $left : $right).": {$directory}\n";
            }
        }

        return ['stdout' => $out, 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => $different ? 1 : 0];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function cmp(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('cmp', $words);
        $paths = $this->operandPaths($args, $cwd);
        if (count($paths) !== 2) {
            throw VfsError::invalid('cmp requires exactly two paths (- for stdin).');
        }
        [$leftPath, $rightPath] = $paths;
        $left = $leftPath === '-' ? (string) $stdin : $this->readFull($agent, $leftPath);
        $right = $rightPath === '-' ? (string) $stdin : $this->readFull($agent, $rightPath);
        $skip = $args->value('-i');
        if ($skip !== null) {
            if (preg_match('/^(\d+)(?::(\d+))?$/', $skip, $m) !== 1) {
                throw VfsError::invalid('cmp -i requires SKIP or SKIP1:SKIP2.');
            }
            $left = substr($left, (int) $m[1]);
            $right = substr($right, (int) ($m[2] ?? $m[1]));
        }
        $limit = $args->int('-n');
        if ($limit !== null) {
            $left = substr($left, 0, $limit);
            $right = substr($right, 0, $limit);
        }
        if ($left === $right) {
            return ['stdout' => '', 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => 0];
        }
        if ($args->has('s')) {
            return ['stdout' => '', 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => 1];
        }

        $length = min(strlen($left), strlen($right));
        if ($args->has('l')) {
            $out = [];
            for ($i = 0; $i < $length; $i++) {
                if ($left[$i] !== $right[$i]) {
                    $out[] = sprintf('%d %3o %3o', $i + 1, ord($left[$i]), ord($right[$i]));
                }
            }
            $stderr = strlen($left) !== strlen($right) ? 'cmp: EOF on '.(strlen($left) < strlen($right) ? $leftPath : $rightPath).' after byte '.$length : '';

            return ['stdout' => $this->linesOut($out), 'stderr' => $stderr, 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => 1];
        }
        $byte = 0;
        while ($byte < $length && $left[$byte] === $right[$byte]) {
            $byte++;
        }
        if ($byte === $length) {
            return ['stdout' => '', 'stderr' => 'cmp: EOF on '.(strlen($left) < strlen($right) ? $leftPath : $rightPath).' after byte '.$length, 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => 1];
        }
        $line = substr_count(substr($left, 0, $byte), "\n") + 1;
        $message = "{$leftPath} {$rightPath} differ: byte ".($byte + 1).", line {$line}";
        if ($args->has('b')) {
            $message .= sprintf(' is %3o %s %3o %s', ord($left[$byte]), $left[$byte], ord($right[$byte]), $right[$byte]);
        }

        return ['stdout' => $message."\n", 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => 1];
    }

    /**
     * Classic sorted merge: column 1 only in FILE1, column 2 only in FILE2,
     * column 3 in both (duplicates are matched one for one).
     *
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function comm(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('comm', $words);
        $paths = $this->operandPaths($args, $cwd);
        if (count($paths) !== 2) {
            throw VfsError::invalid('comm requires exactly two sorted files (- for stdin).');
        }
        $a = $this->splitLines($paths[0] === '-' ? (string) $stdin : $this->readFull($agent, $paths[0]));
        $b = $this->splitLines($paths[1] === '-' ? (string) $stdin : $this->readFull($agent, $paths[1]));
        if ($args->has('--check-order')) {
            foreach ([[$a, $paths[0]], [$b, $paths[1]]] as [$lines, $path]) {
                for ($i = 1; $i < count($lines); $i++) {
                    if (strcmp($lines[$i - 1], $lines[$i]) > 0) {
                        throw VfsError::invalid("comm: file {$path} is not in sorted order (line ".($i + 1).').', ['hint' => 'Sort both inputs first (sort a > /files/a.sorted).']);
                    }
                }
            }
        }
        $show = [1 => ! $args->has('1'), 2 => ! $args->has('2'), 3 => ! $args->has('3')];
        $indent = fn (int $column): string => str_repeat("\t", count(array_filter([$column > 1 && $show[1], $column > 2 && $show[2]])));
        $out = [];
        $i = 0;
        $j = 0;
        while ($i < count($a) || $j < count($b)) {
            $cmp = match (true) {
                $i >= count($a) => 1,
                $j >= count($b) => -1,
                default => strcmp($a[$i], $b[$j]) <=> 0,
            };
            if ($cmp < 0) {
                if ($show[1]) {
                    $out[] = $a[$i];
                }
                $i++;
            } elseif ($cmp > 0) {
                if ($show[2]) {
                    $out[] = $indent(2).$b[$j];
                }
                $j++;
            } else {
                if ($show[3]) {
                    $out[] = $indent(3).$a[$i];
                }
                $i++;
                $j++;
            }
        }

        return ['stdout' => $this->linesOut($out), 'cwd' => $cwd, 'class' => 'read-transform'];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function checksum(User $agent, array $words, string $cwd, ?string $stdin, string $command): array
    {
        $args = $this->args($command, $words);
        $algo = match ($command) {
            'sha1sum' => 'sha1',
            'md5sum' => 'md5',
            default => 'sha256',
        };
        $paths = $this->operandPaths($args, $cwd) ?: ['-'];

        if ($args->has('c')) {
            $lines = [];
            $failed = 0;
            $hexLength = strlen(hash($algo, ''));
            foreach ($paths as $listPath) {
                $list = $listPath === '-' ? (string) $stdin : $this->readFull($agent, $listPath);
                foreach ($this->splitLines($list) as $line) {
                    if (preg_match('/^([a-f0-9]{'.$hexLength.'})\s+\*?(.+)$/i', $line, $m) !== 1) {
                        continue;
                    }
                    $target = $this->path(trim($m[2]), $cwd);
                    try {
                        $ok = hash($algo, $this->readFull($agent, $target)) === strtolower($m[1]);
                        $lines[] = "{$target}: ".($ok ? 'OK' : 'FAILED');
                    } catch (VfsError) {
                        $ok = false;
                        $lines[] = "{$target}: FAILED open or read";
                    }
                    $failed += $ok ? 0 : 1;
                }
            }

            return ['stdout' => $this->linesOut($lines), 'stderr' => $failed > 0 ? "{$command}: WARNING: {$failed} computed checksum(s) did NOT match" : '', 'cwd' => $cwd, 'class' => 'read-transform', 'exit_code' => $failed > 0 ? 1 : 0];
        }

        $lines = [];
        foreach ($paths as $path) {
            $content = $path === '-' ? (string) $stdin : $this->readFull($agent, $path);
            $lines[] = hash($algo, $content).'  '.$path;
        }

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'read-transform'];
    }
}
