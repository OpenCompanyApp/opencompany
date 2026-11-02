<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsAwkProgram;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsPrintf;
use App\Models\User;

/**
 * Field-oriented commands: awk (VfsAwkProgram), xargs, paste.
 *
 * xargs invariant: items from stdin are data. Generated argv words are marked
 * literal, so an item never globs or turns into a flag, and the inner command
 * goes through the same routing, preflight, and write guards as a typed one.
 */
trait ExecutesVfsFieldTransforms
{
    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function awk(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('awk', $words);
        $program = $args->operands[0] ?? throw VfsError::invalid("awk requires a program, e.g. awk '{print \$1}' file.");
        $separator = $args->value('-F');
        if ($separator !== null) {
            $separator = $separator === 't' ? "\t" : VfsPrintf::escapes($separator, false);
        }
        $assignments = [];
        foreach ($args->values('-v') as $assignment) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/s', $assignment, $m) !== 1) {
                throw VfsError::invalid("awk -v expects NAME=VALUE, got \"{$assignment}\".");
            }
            $assignments[$m[1]] = VfsPrintf::escapes($m[2], false);
        }
        $result = VfsAwkProgram::parse($program)->run($this->inputsFrom($agent, $args, $cwd, $stdin, 1), $separator ?? ' ', $assignments);

        return ['stdout' => $result['output'], 'class' => 'read-transform', 'exit_code' => $result['exit_code']];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function xargs(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $spec = VfsCommandSpecRegistry::get('xargs') ?? throw VfsError::invalid('xargs is not registered.');
        $null = false;
        $noRunIfEmpty = false;
        $placeholder = null;
        $maxArgs = null;
        $delimiter = null;
        $start = 0;
        for ($i = 0; $i < count($words); $i++) {
            $word = $words[$i];
            if (($this->argLiteral[$i] ?? false) || ! str_starts_with($word, '-') || $word === '-') {
                break;
            }
            $value = function (string $option, string $attached) use ($words, &$i): string {
                if ($attached !== '') {
                    return $attached;
                }
                if (! array_key_exists($i + 1, $words)) {
                    throw VfsError::invalid("xargs {$option} requires a value.");
                }

                return $words[++$i];
            };
            if ($word === '--') {
                $i++;

                break;
            }
            match (true) {
                $word === '-0' || $word === '--null' => $null = true,
                $word === '-r' || $word === '--no-run-if-empty' => $noRunIfEmpty = true,
                str_starts_with($word, '-I') => $placeholder = $value('-I', substr($word, 2)),
                str_starts_with($word, '--max-args=') => $maxArgs = substr($word, 11),
                $word === '--max-args' => $maxArgs = $value('--max-args', ''),
                str_starts_with($word, '-n') => $maxArgs = $value('-n', substr($word, 2)),
                str_starts_with($word, '--delimiter=') => $delimiter = substr($word, 12),
                str_starts_with($word, '-d') => $delimiter = $value('-d', substr($word, 2)),
                default => throw \App\Domain\Vfs\Core\VfsArgs::unsupportedFlag($spec, $word),
            };
        }
        $start = $i;
        if ($maxArgs !== null && preg_match('/^[1-9]\d*$/', (string) $maxArgs) !== 1) {
            throw VfsError::invalid('xargs -n requires a positive integer.');
        }
        $maxArgs = $maxArgs === null ? null : (int) $maxArgs;
        $baseWords = array_slice($words, $start);
        $baseLiteral = array_slice($this->argLiteral, $start);
        $baseGlob = array_slice($this->argGlob, $start);
        if ($baseWords === []) {
            $baseWords = ['echo'];
            $baseLiteral = [false];
            $baseGlob = [false];
        }

        $input = $stdin ?? '';
        if ($null) {
            $items = explode("\0", rtrim($input, "\0"));
        } elseif ($delimiter !== null) {
            $items = explode(VfsPrintf::escapes($delimiter, false), $input);
        } else {
            $items = array_map('trim', $this->splitLines($input));
        }
        $items = array_values(array_filter($items, fn (string $item): bool => $item !== ''));
        if (count($items) > $budget->maxEntries) {
            $this->noteTruncation('xargs ran '.$budget->maxEntries.' of '.count($items).' items (maxEntries).');
            $items = array_slice($items, 0, $budget->maxEntries);
        }
        if ($items === [] && $noRunIfEmpty) {
            return ['stdout' => '', 'cwd' => $cwd, 'class' => 'read-transform', 'returned' => 0];
        }

        $chunks = $items === [] ? [[]] : array_chunk($items, $placeholder !== null ? 1 : ($maxArgs ?? max(1, count($items))));
        $outputs = '';
        $stderr = [];
        $failed = false;
        $savedLiteral = $this->argLiteral;
        $savedGlob = $this->argGlob;
        try {
            foreach ($chunks as $chunk) {
                $commandWords = $baseWords;
                $literal = $baseLiteral;
                $glob = $baseGlob;
                if ($placeholder !== null) {
                    $item = $chunk[0] ?? '';
                    $replaced = false;
                    foreach ($commandWords as $index => $word) {
                        if ($index > 0 && str_contains($word, $placeholder)) {
                            $commandWords[$index] = str_replace($placeholder, $item, $word);
                            $literal[$index] = true;
                            $glob[$index] = false;
                            $replaced = true;
                        }
                    }
                    if (! $replaced) {
                        $commandWords[] = $item;
                        $literal[] = true;
                        $glob[] = false;
                    }
                } else {
                    foreach ($chunk as $item) {
                        $commandWords[] = $item;
                        $literal[] = true;
                        $glob[] = false;
                    }
                }
                $this->argLiteral = array_slice($literal, 1);
                $this->argGlob = array_slice($glob, 1);
                try {
                    $result = $this->runCommand($agent, $commandWords, $cwd, $budget, null);
                } catch (VfsError $e) {
                    if (in_array($e->errorCode, ['approval_required', 'unsupported'], true)) {
                        throw $e;
                    }
                    $stderr[] = $e->getMessage();
                    $failed = true;

                    continue;
                }
                if ((int) ($result['exit_code'] ?? 0) !== 0) {
                    $failed = true;
                }
                if (($result['stderr'] ?? '') !== '') {
                    $stderr[] = (string) $result['stderr'];
                }
                $chunkOutput = (string) ($result['stdout'] ?? '');
                if ($chunkOutput !== '' && $outputs !== '' && ! str_ends_with($outputs, "\n")) {
                    $outputs .= "\n";
                }
                $outputs .= $chunkOutput;
                $this->assertPipeWithinBudget($outputs);
            }
        } finally {
            $this->argLiteral = $savedLiteral;
            $this->argGlob = $savedGlob;
        }

        return [
            'stdout' => $outputs,
            'stderr' => implode("\n", $stderr),
            'cwd' => $cwd,
            'class' => 'read-transform',
            // GNU: 123 when any invocation failed.
            'exit_code' => $failed ? 123 : 0,
            'returned' => count($items),
            'limit' => $budget->maxEntries,
        ];
    }

    /**
     * @param  list<string>  $words
     * @return array<string, mixed>
     */
    private function paste(User $agent, array $words, string $cwd, ?string $stdin): array
    {
        $args = $this->args('paste', $words);
        // -d LIST cycles through its characters; -d '' joins without one.
        $delimiterList = $args->value('-d');
        $delimiters = $delimiterList === null ? ["\t"] : ($delimiterList === '' ? [''] : mb_str_split(VfsPrintf::escapes($delimiterList, false)));
        $paths = $this->operandPaths($args, $cwd) ?: ['-'];
        $stdinUsed = false;
        $columns = array_map(function (string $path) use ($agent, $stdin, &$stdinUsed): array {
            if ($path === '-') {
                $lines = $stdinUsed ? [] : $this->splitLines((string) $stdin);
                $stdinUsed = true;

                return $lines;
            }

            return $this->splitLines($this->readFull($agent, $path));
        }, $paths);

        $join = function (array $cells) use ($delimiters): string {
            $out = '';
            foreach ($cells as $index => $cell) {
                if ($index > 0) {
                    $out .= $delimiters[($index - 1) % count($delimiters)];
                }
                $out .= $cell;
            }

            return $out;
        };

        if ($args->has('s')) {
            return ['stdout' => $this->linesOut(array_map($join, $columns)), 'cwd' => $cwd, 'class' => 'read-transform'];
        }
        $rows = max(array_map('count', $columns));
        $lines = [];
        for ($row = 0; $row < $rows; $row++) {
            $lines[] = $join(array_map(fn (array $column): string => $column[$row] ?? '', $columns));
        }

        return ['stdout' => $this->linesOut($lines), 'cwd' => $cwd, 'class' => 'read-transform'];
    }
}
