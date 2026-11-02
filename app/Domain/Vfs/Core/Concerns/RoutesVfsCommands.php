<?php

namespace App\Domain\Vfs\Core\Concerns;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandHelp;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
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
        $cmd = $cmd === 'md5' ? 'md5sum' : $cmd;
        if (in_array('--help', $tokens, true)) {
            return [
                'stdout' => VfsCommandHelp::commandMarkdown($cmd),
                'cwd' => $cwd,
                'class' => 'browse',
                'command_spec' => VfsCommandHelp::commandEntry($cmd),
            ];
        }

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
            'readlink' => $this->readlink($tokens, $cwd),
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
            'nl' => $this->nl($tokens, $stdin),
            'rev' => $this->rev($stdin),
            'paste' => $this->paste($agent, $tokens, $cwd, $budget, $stdin),
            'jq' => $this->jq($agent, $tokens, $cwd, $budget, $stdin),
            'sed' => $this->sed($tokens, $stdin),
            'diff' => $this->diff($agent, $tokens, $cwd, $budget),
            'cmp' => $this->cmp($agent, $tokens, $cwd, $budget),
            'comm' => $this->comm($agent, $tokens, $cwd, $budget),
            'sha256sum', 'sha1sum', 'md5sum', 'checksum' => $this->checksum($agent, $tokens, $cwd, $budget, $cmd),
            'tac' => $this->tac($agent, $tokens, $cwd, $budget, $stdin),
            'seq' => $this->seq($tokens),
            'base64' => $this->base64($agent, $tokens, $cwd, $budget, $stdin),
            'gzip' => $this->gzip($agent, $tokens, $cwd, $budget, $stdin),
            'gunzip', 'zcat' => $this->gunzip($agent, $tokens, $cwd, $budget, $stdin, $cmd),
            'zgrep' => $this->zgrep($agent, $tokens, $cwd, $budget, $stdin),
            'tar' => $this->tar($agent, $tokens, $cwd, $budget, $stdin),
            'zip' => $this->zip($agent, $tokens, $cwd, $budget),
            'unzip' => $this->unzip($agent, $tokens, $cwd, $budget, $stdin),
            'xxd' => $this->xxd($agent, $tokens, $cwd, $budget, $stdin),
            'strings' => $this->strings($agent, $tokens, $cwd, $budget, $stdin),
            'iconv' => $this->iconv($agent, $tokens, $cwd, $budget, $stdin),
            'column' => $this->column($tokens, $stdin),
            'fold' => $this->fold($tokens, $stdin),
            'fmt' => $this->fmt($tokens, $stdin),
            'expand' => $this->expand($tokens, $stdin),
            'unexpand' => $this->unexpand($tokens, $stdin),
            'tsort' => $this->tsort($stdin),
            'look' => $this->look($agent, $tokens, $cwd, $budget, $stdin),
            'join' => $this->join($agent, $tokens, $cwd, $budget),
            'shuf' => $this->shuf($tokens, $stdin),
            'bc' => $this->bc($tokens, $stdin),
            'expr' => $this->expr($tokens),
            'which' => $this->which($tokens, $cwd),
            'type' => $this->type($tokens, $cwd),
            'help' => $this->help($tokens, $cwd),
            'man' => $this->help($tokens, $cwd),
            'command' => $this->commandBuiltin($tokens, $cwd),
            'env', 'printenv' => $this->env($agent, $tokens, $cwd),
            'date' => $this->dateCommand($tokens, $cwd),
            'mkdir' => $this->mkdir($agent, $tokens, $cwd),
            'touch' => $this->touch($agent, $tokens, $cwd),
            'cp' => $this->copy($agent, $tokens, $cwd),
            'mv' => $this->move($agent, $tokens, $cwd),
            'rm', 'rmdir' => $this->remove($agent, $tokens, $cwd),
            'ln' => $this->link($agent, $tokens, $cwd),
            'mktemp' => $this->mktemp($agent, $tokens, $cwd),
            'split' => $this->split($agent, $tokens, $cwd, $budget),
            'csplit' => $this->csplit($agent, $tokens, $cwd, $budget),
            'truncate' => $this->truncate($agent, $tokens, $cwd),
            'tee' => $this->tee($agent, $tokens, $cwd, $stdin),
            default => $this->unsupportedKnownCommand($cmd),
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
            $cmd = $cmd === 'md5' ? 'md5sum' : $cmd;

            $this->assertRecognizedCommandSupported($cmd, $tokens);
        }
    }

    private function assertRecognizedCommandSupported(string $cmd, array $tokens): void
    {
        $canonical = VfsCommandSpecRegistry::canonical($cmd) ?? $cmd;
        $spec = VfsCommandSpecRegistry::get($canonical);
        if ($spec === null) {
            throw VfsError::unsupported("Unsupported VFS command: {$cmd}", [
                'command' => $cmd,
                'suggestion' => 'Use pwd, ls, tree, find, rg, grep, cat, head, tail, wc, stat, file, du, jq, diff, or the vfs_patch/vfs_write tools for edits.',
            ]);
        }

        if (in_array('--help', $tokens, true)) {
            return;
        }
        $this->assertSupportedOptionsForCommand($tokens, $canonical);
        if ($spec->status !== 'implemented') {
            throw VfsError::unsupported($spec->unsupportedReason ?? "Unsupported VFS command: {$cmd}", [
                'command' => $cmd,
                'status' => $spec->status,
                'suggestion' => $spec->suggestion,
            ]);
        }
    }

    private function unsupportedKnownCommand(string $cmd): never
    {
        $spec = VfsCommandSpecRegistry::get($cmd);
        throw VfsError::unsupported($spec?->unsupportedReason ?? "Unsupported VFS command: {$cmd}", [
            'command' => $cmd,
            'status' => $spec?->status,
            'suggestion' => $spec?->suggestion ?? 'Use pwd, ls, tree, find, rg, grep, cat, head, tail, wc, stat, file, du, jq, diff, or the vfs_patch/vfs_write tools for edits.',
        ]);
    }
}
