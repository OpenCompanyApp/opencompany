<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsError;
use App\Models\User;

/**
 * Dispatches canonical command names to their focused command-family handlers.
 *
 * The executor owns shell orchestration; this trait owns the command table and
 * preflight support checks so the orchestration class does not grow with every
 * unix command added to the VFS surface.
 */
trait RoutesVfsCommands
{
    private function runCommand(User $agent, array $tokens, string $cwd, VfsBudget $budget, ?string $stdin): array
    {
        $cmd = array_shift($tokens);
        array_shift($this->lastTokenQuoteMask);
        if ($cmd === 'll') {
            array_unshift($tokens, '-l');
        }
        $cmd = $cmd === 'dir' || $cmd === 'll' ? 'ls' : $cmd;
        $cmd = $cmd === 'more' || $cmd === 'less' ? 'cat' : $cmd;

        return match ($cmd) {
            'pwd' => ['stdout' => $cwd, 'cwd' => $cwd, 'class' => 'browse'],
            'cd' => $this->cd($agent, $tokens, $cwd),
            'ls' => $this->ls($agent, $tokens, $cwd, $budget),
            'tree' => $this->tree($agent, $tokens, $cwd, $budget),
            'find' => $this->find($agent, $tokens, $cwd, $budget),
            'cat' => $this->cat($agent, $tokens, $cwd, $budget, $stdin),
            'head' => $this->headTail($agent, $tokens, $cwd, $budget, $stdin, true),
            'tail' => $this->headTail($agent, $tokens, $cwd, $budget, $stdin, false),
            'wc' => $this->wc($agent, $tokens, $cwd, $budget, $stdin),
            'stat' => $this->stat($agent, $tokens, $cwd),
            'file' => $this->file($agent, $tokens, $cwd),
            'du' => $this->du($agent, $tokens, $cwd, $budget),
            'realpath' => $this->pathTransform($tokens, $cwd, 'realpath'),
            'dirname' => $this->pathTransform($tokens, $cwd, 'dirname'),
            'basename' => $this->pathTransform($tokens, $cwd, 'basename'),
            'grep', 'fgrep', 'rg', 'egrep' => $this->grep($agent, $tokens, $cwd, $budget, $stdin, in_array($cmd, ['rg', 'egrep'], true)),
            'search' => $this->grep($agent, $tokens, $cwd, $budget, $stdin, false),
            'echo' => $this->echo($tokens),
            'true' => ['stdout' => '', 'cwd' => $cwd, 'class' => 'read-transform'],
            'false' => throw VfsError::invalid('false returned non-zero status.'),
            'printf' => $this->printf($tokens),
            'sort' => $this->sort($agent, $tokens, $cwd, $budget, $stdin),
            'uniq' => $this->uniq($tokens, $stdin),
            'cut' => $this->cut($tokens, $stdin),
            'awk' => $this->awk($tokens, $stdin),
            'xargs' => $this->xargs($agent, $tokens, $cwd, $budget, $stdin),
            'tr' => $this->tr($tokens, $stdin),
            'nl' => $this->nl($stdin),
            'rev' => $this->rev($stdin),
            'paste' => $this->paste($agent, $tokens, $cwd, $budget, $stdin),
            'jq' => $this->jq($agent, $tokens, $cwd, $budget, $stdin),
            'sed' => $this->sed($tokens, $stdin),
            'diff' => $this->diff($agent, $tokens, $cwd, $budget),
            'cmp' => $this->cmp($agent, $tokens, $cwd, $budget),
            'comm' => $this->comm($agent, $tokens, $cwd, $budget),
            'sha256sum', 'sha1sum', 'md5sum', 'checksum' => $this->checksum($agent, $tokens, $cwd, $budget, $cmd),
            'which' => $this->which($tokens, $cwd),
            'type' => $this->type($tokens, $cwd),
            'command' => $this->commandBuiltin($tokens, $cwd),
            'env', 'printenv' => $this->env($agent, $tokens, $cwd),
            'date' => ['stdout' => now()->toIso8601String(), 'cwd' => $cwd, 'class' => 'browse'],
            'mkdir' => $this->mkdir($agent, $tokens, $cwd),
            'touch' => $this->touch($agent, $tokens, $cwd),
            'cp' => $this->copy($agent, $tokens, $cwd),
            'mv' => $this->move($agent, $tokens, $cwd),
            'rm', 'rmdir' => $this->remove($agent, $tokens, $cwd),
            'truncate' => $this->truncate($agent, $tokens, $cwd),
            'tee' => $this->tee($agent, $tokens, $cwd, $stdin),
            default => throw VfsError::unsupported("Unsupported VFS command: {$cmd}", [
                'command' => $cmd,
                'suggestion' => 'Use pwd, ls, tree, find, rg, grep, cat, head, tail, wc, stat, file, du, jq, diff, or the vfs_patch/vfs_write tools for edits.',
            ]),
        };
    }

    private function assertCommandGroupSupported(string $command, string $cwd): void
    {
        foreach ($this->splitPipes($command) as $segment) {
            $tokens = $this->tokenize($segment);
            if ($tokens === []) {
                continue;
            }
            [$tokens, $redirections] = $this->extractRedirections($tokens, $cwd);
            if ($this->hasUnsupportedStderrFileRedirect($redirections)) {
                throw VfsError::unsupported('stderr file redirection is not supported; use 2>&1 to merge errors into stdout or 2>/dev/null to suppress them.');
            }
            $cmd = array_shift($tokens);
            array_shift($this->lastTokenQuoteMask);
            if ($cmd === 'll') {
                array_unshift($tokens, '-l');
            }
            $cmd = $cmd === 'dir' || $cmd === 'll' ? 'ls' : $cmd;
            $cmd = $cmd === 'more' || $cmd === 'less' ? 'cat' : $cmd;

            match ($cmd) {
                'pwd', 'true', 'false', 'date' => null,
                'cd', 'cat', 'stat', 'file', 'du', 'realpath', 'dirname', 'basename', 'printf', 'sed', 'which', 'type', 'env', 'printenv', 'mkdir', 'touch', 'cp', 'mv', 'rm', 'rmdir', 'tee', 'jq' => $this->assertSupportedOptions($tokens, $cmd),
                'ls' => $this->assertSupportedOptions($tokens, 'ls', ['1', 'a', 'l', 'R']),
                'tree' => $this->assertSupportedOptions($tokens, 'tree', [], ['-L', '--level']),
                'find' => $this->assertSupportedOptions($tokens, 'find', [], ['-name', '-type', '-maxdepth', '-maxDepth', '--max-depth'], ['-print']),
                'head', 'tail' => $this->assertSupportedOptions($tokens, $cmd, ['-N'], ['-n', '--lines', '-c', '--bytes']),
                'wc' => $this->assertSupportedOptions($tokens, 'wc', ['l', 'w', 'c']),
                'grep', 'fgrep', 'rg', 'egrep', 'search' => $this->assertSupportedOptions($tokens, in_array($cmd, ['rg', 'egrep'], true) ? 'rg' : 'grep', ['c', 'l', 'n', 'r', 'R', 'i', 'o'], ['--max-count', '-m', '--max-depth']),
                'echo' => $this->assertSupportedOptions($tokens, 'echo', ['n', 'e']),
                'sort' => $this->assertSupportedOptions($tokens, 'sort', ['r', 'n', 'u'], ['-k']),
                'uniq' => $this->assertSupportedOptions($tokens, 'uniq', ['c', 'd', 'u']),
                'cut' => $this->assertSupportedOptions($tokens, 'cut', [], ['-d', '-f']),
                'awk' => $this->assertSupportedOptions($tokens, 'awk', [], ['-F']),
                'xargs' => $this->assertSupportedOptions($tokens, 'xargs', [], ['-I', '-n', '--max-args']),
                'tr' => $this->assertSupportedOptions($tokens, 'tr', ['d']),
                'nl', 'rev' => $this->assertSupportedOptions($tokens, $cmd),
                'paste' => $this->assertSupportedOptions($tokens, 'paste'),
                'diff', 'cmp', 'comm' => $this->assertSupportedOptions($tokens, $cmd),
                'sha256sum', 'sha1sum', 'md5sum', 'checksum' => $this->assertSupportedOptions($tokens, $cmd),
                'command' => (($tokens[0] ?? null) === '-v')
                    ? null
                    : throw VfsError::unsupported('Only command -v is supported by the VFS command builtin.'),
                'truncate' => $this->assertSupportedOptions($tokens, 'truncate', [], ['-s', '--size']),
                default => throw VfsError::unsupported("Unsupported VFS command: {$cmd}", [
                    'command' => $cmd,
                    'suggestion' => 'Use pwd, ls, tree, find, rg, grep, cat, head, tail, wc, stat, file, du, jq, diff, or the vfs_patch/vfs_write tools for edits.',
                ]),
            };
        }
    }
}
