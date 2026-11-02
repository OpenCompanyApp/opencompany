<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsArgs;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandHelp;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsShellCommand;
use App\Models\User;

/**
 * Dispatches canonical command names to their command-family handlers.
 *
 * Owns: alias normalization (ll, dir, less, more, md5, egrep, fgrep, search,
 * rmdir, `[`), `--help`, and the support preflight that runs for every command
 * of a line before anything executes. The executor owns orchestration; the
 * handlers own semantics.
 *
 * Invariant: alias normalization happens here and only here, and it keeps
 * the argv quote masks aligned (inserting `-l` for `ll` inserts an unquoted
 * mask entry), so quoted operands never glob or turn into flags.
 */
trait RoutesVfsCommands
{
    /**
     * Commands that parse their own argv (operands may legitimately start
     * with "-", or option parsing stops at the first operand).
     */
    private const CUSTOM_ARGV_COMMANDS = ['echo', 'printf', 'find', 'test', 'expr', 'xargs', 'seq', 'env', 'printenv', 'command', 'true', 'false', 'cd', 'bc'];

    /**
     * @param  list<string>  $words  argv including the command name
     * @return array<string, mixed>
     */
    private function runCommand(User $agent, array $words, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $name = (string) array_shift($words);
        [$cmd, $words] = $this->normalizeInvocation($name, $words);
        if ($this->wantsHelp($words)) {
            return [
                'stdout' => VfsCommandHelp::commandMarkdown($cmd)."\n",
                'cwd' => $cwd,
                'class' => 'browse',
                'command_spec' => VfsCommandHelp::commandEntry($cmd),
            ];
        }
        $this->assertCommandSupported($cmd, $words);

        return match ($cmd) {
            'pwd' => ['stdout' => $cwd."\n", 'cwd' => $cwd, 'class' => 'browse'],
            'cd' => $this->cd($agent, $words, $cwd),
            'ls' => $this->ls($agent, $words, $cwd, $budget),
            'tree' => $this->tree($agent, $words, $cwd, $budget),
            'find' => $this->find($agent, $words, $cwd, $budget),
            'cat' => $this->cat($agent, $words, $cwd, $budget, $stdin),
            'head' => $this->headTail($agent, $words, $cwd, $stdin, true),
            'tail' => $this->headTail($agent, $words, $cwd, $stdin, false),
            'wc' => $this->wc($agent, $words, $cwd, $stdin),
            'stat' => $this->stat($agent, $words, $cwd),
            'file' => $this->file($agent, $words, $cwd),
            'du' => $this->du($agent, $words, $cwd, $budget),
            'realpath', 'dirname', 'basename' => $this->pathTransform($words, $cwd, $cmd),
            'readlink' => $this->readlink($words, $cwd),
            'grep', 'rg' => $this->grep($agent, $words, $cwd, $budget, $stdin, $cmd),
            'echo' => $this->echo($words),
            'true' => ['stdout' => '', 'class' => 'read-transform'],
            'false' => ['stdout' => '', 'class' => 'read-transform', 'exit_code' => 1],
            'test' => $this->testCommand($agent, $words, $cwd),
            'printf' => $this->printf($words),
            'sort' => $this->sort($agent, $words, $cwd, $stdin),
            'uniq' => $this->uniq($agent, $words, $cwd, $stdin),
            'cut' => $this->cut($agent, $words, $cwd, $stdin),
            'awk' => $this->awk($agent, $words, $cwd, $stdin),
            'xargs' => $this->xargs($agent, $words, $cwd, $budget, $stdin),
            'tr' => $this->tr($agent, $words, $cwd, $stdin),
            'nl' => $this->nl($agent, $words, $cwd, $stdin),
            'rev' => $this->rev($agent, $words, $cwd, $stdin),
            'paste' => $this->paste($agent, $words, $cwd, $stdin),
            'jq' => $this->jq($agent, $words, $cwd, $stdin),
            'sed' => $this->sed($agent, $words, $cwd, $stdin),
            'diff' => $this->diff($agent, $words, $cwd, $budget, $stdin),
            'cmp' => $this->cmp($agent, $words, $cwd, $stdin),
            'comm' => $this->comm($agent, $words, $cwd, $stdin),
            'sha256sum', 'sha1sum', 'md5sum', 'checksum' => $this->checksum($agent, $words, $cwd, $stdin, $cmd),
            'tac' => $this->tac($agent, $words, $cwd, $stdin),
            'seq' => $this->seq($words),
            'base64' => $this->base64($agent, $words, $cwd, $stdin),
            'gzip' => $this->gzip($agent, $words, $cwd, $budget, $stdin),
            'gunzip', 'zcat' => $this->gunzip($agent, $words, $cwd, $budget, $stdin, $cmd),
            'zgrep' => $this->zgrep($agent, $words, $cwd, $budget, $stdin),
            'tar' => $this->tar($agent, $words, $cwd, $budget, $stdin),
            'zip' => $this->zip($agent, $words, $cwd, $budget),
            'unzip' => $this->unzip($agent, $words, $cwd, $budget, $stdin),
            'xxd' => $this->xxd($agent, $words, $cwd, $stdin),
            'strings' => $this->strings($agent, $words, $cwd, $stdin),
            'iconv' => $this->iconv($agent, $words, $cwd, $stdin),
            'column' => $this->column($agent, $words, $cwd, $stdin),
            'fold' => $this->fold($agent, $words, $cwd, $stdin),
            'fmt' => $this->fmt($agent, $words, $cwd, $stdin),
            'expand' => $this->expand($agent, $words, $cwd, $stdin),
            'unexpand' => $this->unexpand($agent, $words, $cwd, $stdin),
            'tsort' => $this->tsort($agent, $words, $cwd, $stdin),
            'look' => $this->look($agent, $words, $cwd, $stdin),
            'join' => $this->join($agent, $words, $cwd, $stdin),
            'shuf' => $this->shuf($agent, $words, $cwd, $stdin),
            'bc' => $this->bc($words, $stdin),
            'expr' => $this->expr($words),
            'which' => $this->which($words, $cwd),
            'type' => $this->type($words, $cwd),
            'help', 'man' => $this->help($words, $cwd),
            'command' => $this->commandBuiltin($words, $cwd),
            'env', 'printenv' => $this->env($agent, $words, $cwd),
            'date' => $this->dateCommand($words, $cwd),
            'mkdir' => $this->mkdir($agent, $words, $cwd),
            'touch' => $this->touch($agent, $words, $cwd),
            'cp' => $this->copy($agent, $words, $cwd),
            'mv' => $this->move($agent, $words, $cwd),
            'rm' => $this->remove($agent, $words, $cwd),
            'ln' => $this->link($agent, $words, $cwd),
            'mktemp' => $this->mktemp($agent, $words, $cwd),
            'split' => $this->split($agent, $words, $cwd),
            'csplit' => $this->csplit($agent, $words, $cwd),
            'truncate' => $this->truncate($agent, $words, $cwd),
            'tee' => $this->tee($agent, $words, $cwd, $stdin),
            default => $this->unsupportedKnownCommand($cmd),
        };
    }

    /**
     * Map aliases onto canonical commands with the flags they imply.
     *
     * @param  list<string>  $words
     * @return array{0: string, 1: list<string>}
     */
    private function normalizeInvocation(string $name, array $words): array
    {
        $prefix = match ($name) {
            'll' => ['-l'],
            'egrep' => ['-E'],
            'fgrep' => ['-F'],
            'rmdir' => ['-d'],
            default => [],
        };
        if ($name === '[') {
            if (end($words) !== ']') {
                throw VfsError::invalid('[: missing closing ].', ['hint' => 'Write [ EXPR ] with spaces, e.g. [ -f /files/x ].']);
            }
            array_pop($words);
            array_pop($this->argLiteral);
            array_pop($this->argGlob);
        }
        if ($prefix !== []) {
            array_unshift($words, ...$prefix);
            array_unshift($this->argLiteral, ...array_fill(0, count($prefix), false));
            array_unshift($this->argGlob, ...array_fill(0, count($prefix), false));
        }
        $canonical = VfsCommandSpecRegistry::canonical($name) ?? $name;
        if ($canonical === 'grep' && $name === 'search') {
            $canonical = 'grep';
        }

        return [$canonical, $words];
    }

    /**
     * @param  list<string>  $words
     */
    private function wantsHelp(array $words): bool
    {
        foreach ($words as $index => $word) {
            if ($word === '--help' && ! ($this->argLiteral[$index] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate every command of the line before any of them runs.
     *
     * @param  list<array{operator: string|null, pipeline: list<VfsShellCommand>}>  $groups
     */
    private function preflight(array $groups): void
    {
        foreach ($groups as $group) {
            foreach ($group['pipeline'] as $segment) {
                $words = $segment->words;
                $name = (string) array_shift($words);
                $this->argLiteral = array_slice($segment->literal, 1);
                $this->argGlob = array_slice($segment->glob, 1);
                [$cmd, $words] = $this->normalizeInvocation($name, $words);
                if ($this->wantsHelp($words)) {
                    if (VfsCommandSpecRegistry::get($cmd) === null) {
                        $this->unknownCommand($name);
                    }

                    continue;
                }
                $this->assertCommandSupported($cmd, $words);
            }
        }
        $this->argLiteral = [];
        $this->argGlob = [];
    }

    /**
     * Command exists, is implemented, and its flags are supported. Specific
     * refusals (tail -f, ln -s, ...) run before generic flag parsing so the
     * agent gets the real reason.
     *
     * @param  list<string>  $words
     */
    private function assertCommandSupported(string $cmd, array $words): void
    {
        $spec = VfsCommandSpecRegistry::get($cmd);
        if ($spec === null) {
            $this->unknownCommand($cmd);
        }
        if ($spec->status !== 'implemented') {
            $this->unsupportedKnownCommand($cmd);
        }
        $this->assertNoRefusedFlags($cmd, $words);
        if (! in_array($spec->name, self::CUSTOM_ARGV_COMMANDS, true)) {
            VfsArgs::parse($spec, $words, $this->argLiteral, $this->argGlob);

            return;
        }
        // Commands with their own argv grammar still validate statically.
        match ($spec->name) {
            'find' => $this->assertFindExpressionSupported($words),
            'xargs' => $this->assertXargsSupported($words),
            'env', 'printenv' => $this->assertEnvSupported($words),
            default => null,
        };
    }

    /**
     * Validate find's expression without touching storage.
     *
     * @param  list<string>  $words
     */
    private function assertFindExpressionSupported(array $words): void
    {
        $index = 0;
        while ($index < count($words) && ! $this->isFindExpressionStart($words[$index], $this->argLiteral[$index] ?? false)) {
            $index++;
        }
        $expression = [];
        $tokens = array_slice($words, $index);
        for ($i = 0; $i < count($tokens); $i++) {
            if (in_array($tokens[$i], ['-maxdepth', '-maxDepth', '--max-depth', '-mindepth'], true)) {
                $i++;

                continue;
            }
            if (str_starts_with($tokens[$i], '--max-depth=')) {
                continue;
            }
            $expression[] = $tokens[$i];
        }
        foreach (['-exec', '-execdir', '-delete', '-ok'] as $refused) {
            if (in_array($refused, $expression, true)) {
                throw VfsError::unsupported('find -exec/-delete are not supported in VFS find.', [
                    'suggestion' => 'Pipe into xargs instead: find /files/tmp -name "*.log" -print0 | xargs -0 rm',
                ]);
            }
        }
        if ($expression !== []) {
            $position = 0;
            $this->parseFindOr($expression, $position);
            if ($position !== count($expression)) {
                throw VfsError::invalid('find: unexpected expression token "'.$expression[$position].'".');
            }
        }
    }

    /**
     * Validate xargs options and the inner command's name and flags.
     *
     * @param  list<string>  $words
     */
    private function assertXargsSupported(array $words): void
    {
        $spec = VfsCommandSpecRegistry::get('xargs');
        for ($i = 0; $i < count($words); $i++) {
            $word = $words[$i];
            if (($this->argLiteral[$i] ?? false) || ! str_starts_with($word, '-') || $word === '-') {
                break;
            }
            if ($word === '--') {
                $i++;

                break;
            }
            if (in_array($word, ['-0', '--null', '-r', '--no-run-if-empty'], true) || preg_match('/^(-I|-n|-d)./', $word) === 1 || str_starts_with($word, '--max-args=') || str_starts_with($word, '--delimiter=')) {
                continue;
            }
            if (in_array($word, ['-I', '-n', '-d', '--max-args'], true)) {
                $i++;

                continue;
            }
            throw VfsArgs::unsupportedFlag($spec ?? throw VfsError::invalid('xargs'), $word);
        }
        $inner = array_slice($words, $i);
        if ($inner === []) {
            return;
        }
        $savedLiteral = $this->argLiteral;
        $savedGlob = $this->argGlob;
        $this->argLiteral = array_slice($savedLiteral, $i + 1);
        $this->argGlob = array_slice($savedGlob, $i + 1);
        try {
            $name = (string) array_shift($inner);
            [$cmd, $innerWords] = $this->normalizeInvocation($name, $inner);
            $this->assertCommandSupported($cmd, $innerWords);
        } finally {
            $this->argLiteral = $savedLiteral;
            $this->argGlob = $savedGlob;
        }
    }

    /**
     * @param  list<string>  $words
     */
    private function assertEnvSupported(array $words): void
    {
        foreach ($words as $word) {
            if (str_starts_with($word, '-') || str_contains($word, '=')) {
                throw VfsError::unsupported('env assignment, options, and command execution forms are not supported in VFS commands.', ['suggestion' => 'The VFS has no variables; use literal values, or Code Mode (code_exec).']);
            }
        }
    }

    /**
     * Flags the VFS refuses on purpose, each with its reason and alternative.
     *
     * @param  list<string>  $words
     */
    private function assertNoRefusedFlags(string $cmd, array $words): void
    {
        $options = [];
        foreach ($words as $index => $word) {
            if ($word === '--') {
                break;
            }
            if (! ($this->argLiteral[$index] ?? false) && str_starts_with($word, '-') && $word !== '-') {
                $options[] = $word;
            }
        }
        $hasShort = function (string $letter) use ($options): bool {
            foreach ($options as $option) {
                // Only pure flag clusters (-sf), never attached values (-vfile=x).
                if (preg_match('/^-[A-Za-z]+$/', $option) === 1 && str_contains(substr($option, 1), $letter)) {
                    return true;
                }
            }

            return false;
        };

        $refusal = match (true) {
            $cmd === 'tail' && ($hasShort('f') || $hasShort('F') || in_array('--follow', $options, true)) => ['tail -f/--follow is not supported by bounded VFS exec.', 'Re-run tail later to see new content.'],
            $cmd === 'ln' && ($hasShort('s') || in_array('--symbolic', $options, true)) => ['ln -s requires first-class VFS symlink entries, which are not enabled.', 'Use cp, or reference the canonical /files/by-id path.'],
            $cmd === 'touch' && ($hasShort('d') || $hasShort('r') || $hasShort('t')) => ['touch -d/-r/-t are not supported because VFS does not expose manual mtime changes.', 'Use touch without a time to update the modification time.'],
            $cmd === 'tar' && ($hasShort('j') || $hasShort('J')) => ['tar bzip2/xz compression is not supported; use -z for gzip or plain tar.', null],
            $cmd === 'iconv' && ($hasShort('o') || in_array('--output', $options, true)) => ['iconv -o writes are not supported; redirect stdout to a writable /files path instead.', null],
            $cmd === 'awk' && ($hasShort('f') || in_array('--file', $options, true)) && ! in_array('-F', $options, true) => ['awk -f program files are not supported by VFS awk.', "Pass the program inline: awk '...' file."],
            $cmd === 'diff' && ($hasShort('e') || in_array('--ed', $options, true)) => ['diff -e editor scripts are not supported.', 'Use diff -u for a unified diff.'],
            $cmd === 'sed' && $this->sedInPlaceSuffix($words) !== null => ['sed -i with a backup suffix ('.$this->sedInPlaceSuffix($words).') is not supported.', 'Use plain -i; every governed write is versioned, or copy the file first with cp.'],
            $cmd === 'cd' && in_array('-', $words, true) => ['cd - (previous directory) is not supported.', 'cd to an explicit path.'],
            default => null,
        };
        if ($refusal !== null) {
            throw VfsError::unsupported($refusal[0], array_filter(['command' => $cmd, 'suggestion' => $refusal[1]]));
        }
    }

    private function unknownCommand(string $cmd): never
    {
        throw VfsError::unsupported("Unknown VFS command: {$cmd}. Run `help` to list commands.", [
            'command' => $cmd,
            'status' => 'unknown',
            'suggestion' => 'Run `help` for the command list, or use vfs_edit/vfs_patch/vfs_write for edits and Code Mode (code_exec) for scripting.',
        ]);
    }

    private function unsupportedKnownCommand(string $cmd): never
    {
        $spec = VfsCommandSpecRegistry::get($cmd);
        if ($spec === null) {
            $this->unknownCommand($cmd);
        }

        throw VfsError::unsupported($spec->unsupportedReason ?? "Unsupported VFS command: {$cmd}", [
            'command' => $cmd,
            'status' => $spec->status,
            'suggestion' => $spec->suggestion ?? 'Run `help` to list commands.',
        ]);
    }
}
