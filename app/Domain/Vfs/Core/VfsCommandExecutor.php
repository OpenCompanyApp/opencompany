<?php

namespace App\Domain\Vfs\Core;

use App\Domain\Vfs\Core\Concerns\ExecutesVfsArchiveCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsBinaryInspectionCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsBrowseCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsDiscoveryCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsFindCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsMetadataCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsMutationCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsPathCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsReadCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsSearchCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsTransformCommands;
use App\Domain\Vfs\Core\Concerns\ParsesVfsShell;
use App\Domain\Vfs\Core\Concerns\ResolvesVfsRedirectionEffects;
use App\Domain\Vfs\Core\Concerns\RoutesVfsCommands;
use App\Domain\Vfs\Core\Concerns\ValidatesVfsCommandOptions;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\OpenCompany\Policy\VfsMutationPolicy;
use App\Models\User;

/**
 * Safe unix-like command interpreter for the virtual filesystem.
 *
 * This class owns orchestration only: command chaining, pipes, cwd movement, and
 * dispatch. The command families live in focused traits so future VFS work can
 * extend a narrow surface without turning the executor back into a god file.
 */
class VfsCommandExecutor
{
    use ExecutesVfsArchiveCommands;
    use ExecutesVfsBinaryInspectionCommands;
    use ExecutesVfsBrowseCommands;
    use ExecutesVfsDiscoveryCommands;
    use ExecutesVfsFindCommands;
    use ExecutesVfsMetadataCommands;
    use ExecutesVfsMutationCommands;
    use ExecutesVfsPathCommands;
    use ExecutesVfsReadCommands;
    use ExecutesVfsSearchCommands;
    use ExecutesVfsTransformCommands;
    use ParsesVfsShell;
    use ResolvesVfsRedirectionEffects;
    use RoutesVfsCommands;
    use ValidatesVfsCommandOptions;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function commandCatalog(): array
    {
        return VfsCommandCatalog::all();
    }

    public function __construct(
        private readonly OpenCompanyVfs $vfs,
        private readonly VfsMutationPolicy $mutationPolicy,
    ) {}

    private ?User $currentAgent = null;

    /** @var array<string, mixed> */
    private array $currentToolParameters = [];

    private string $currentCommand = '';

    /**
     * Budget of the execution in progress. Its truncation counter tells the
     * write paths whether stdout may carry a display truncation line.
     */
    private ?VfsBudget $currentBudget = null;

    /**
     * Classify a command line's effect without executing it: 'write' when any
     * command in any group or pipeline can mutate, else 'read'.
     *
     * Used by Code Mode (CodeBridge) to give `app.vfs.shell(...)` a durable
     * write receipt and a `write` effect in the call ledger: the registry
     * types vfs_exec as a read because most commands are. A command counts
     * as a write when its spec class is write, destructive, or binary-archive
     * (archive list modes included, conservatively), when it declares
     * `writes`, when xargs could run a writing command, or when stdout/stderr
     * is redirected to a file. Anything that fails to parse is a write: an
     * unclassifiable command must never be logged as a retryable read.
     *
     * Pure: no agent, workspace, or storage access.
     *
     * @return 'read'|'write'
     */
    public function classifyEffect(string $command): string
    {
        try {
            $this->assertNoUnsupportedShellSyntax($command);
            foreach ($this->splitCommandGroups($command) as $group) {
                foreach ($this->splitPipes($group['command']) as $segment) {
                    $tokens = $this->tokenize($segment);
                    if ($tokens === []) {
                        continue;
                    }

                    [$tokens, $redirections] = $this->extractRedirections($tokens, '/');
                    foreach ($redirections as $redirect) {
                        if ($redirect['stream'] !== 'stdin' && $redirect['mode'] !== 'merge' && $redirect['path'] !== '/dev/null') {
                            return 'write';
                        }
                    }

                    $name = (string) ($tokens[0] ?? '');
                    // xargs runs its argv as a command; any writing command
                    // name among its tokens makes the whole call a write.
                    $candidates = VfsCommandSpecRegistry::canonical($name) === 'xargs' ? $tokens : [$name];
                    foreach ($candidates as $candidate) {
                        $spec = VfsCommandSpecRegistry::get((string) $candidate);
                        if ($spec !== null && (in_array($spec->class, ['write', 'destructive', 'binary-archive'], true) || $spec->writes !== null)) {
                            return 'write';
                        }
                    }
                }
            }
        } catch (\Throwable) {
            return 'write';
        }

        return 'read';
    }

    private function agentForPathExpansion(): User
    {
        return $this->currentAgent ?? throw VfsError::invalid('VFS command has no active agent context.');
    }

    /**
     * @param  array<string, mixed>|null  $toolParameters
     * @return array<string, mixed>
     */
    public function execute(User $agent, string $command, string $cwd = '/', ?VfsBudget $budget = null, ?array $toolParameters = null): array
    {
        $this->currentAgent = $agent;
        $this->currentCommand = $command;
        $this->currentToolParameters = $toolParameters ?? ['command' => $command, 'cwd' => $cwd];
        $this->agentForPathExpansion();
        // Invariant: the executor evaluates mutation policy (and may create
        // approvals) before it reaches the facade, so it must check the
        // workspace binding itself.
        $this->vfs->assertBoundWorkspace($agent);
        $budget ??= new VfsBudget;
        $this->currentBudget = $budget;
        $cwd = $this->vfs->normalizePath($cwd);
        $stdout = '';
        $result = null;

        $this->assertNoUnsupportedShellSyntax($command);
        $groups = $this->splitCommandGroups($command);
        $lastOk = true;
        foreach ($groups as $index => $group) {
            $operator = $group['operator'];
            if ($operator === '&&' && ! $lastOk) {
                $this->assertCommandGroupSupported($group['command'], $cwd);

                continue;
            }
            if ($operator === '||' && $lastOk) {
                $this->assertCommandGroupSupported($group['command'], $cwd);

                continue;
            }

            $nextOperator = $groups[$index + 1]['operator'] ?? null;
            $pipeline = $this->splitPipes($group['command']);
            $input = null;
            $redirections = [];

            try {
                foreach ($pipeline as $pipeIndex => $segment) {
                    $tokens = $this->tokenize($segment);
                    if ($tokens === []) {
                        continue;
                    }

                    [$tokens, $redirections] = $this->extractRedirections($tokens, $cwd);
                    $commandInput = $input;
                    if (($stdinPath = $this->stdinRedirectPath($redirections)) !== null) {
                        $commandInput = $stdinPath === '/dev/null'
                            ? ''
                            : $this->vfs->read($agent, $stdinPath, $budget);
                    }
                    try {
                        $result = $this->runCommand($agent, $tokens, $cwd, $budget, $commandInput);
                        $result = $this->applyRedirections($agent, $result, $redirections);
                    } catch (VfsError $e) {
                        if ($this->hasUnsupportedStderrFileRedirect($redirections)) {
                            throw VfsError::unsupported('stderr file redirection is not supported; use 2>&1 to merge errors into stdout or 2>/dev/null to suppress them.');
                        }

                        $stderrMergedToStdout = $this->stderrMergedToStdout($redirections);
                        $stderrMergeFile = $this->stderrMergeFile($redirections);
                        if ($stderrMergedToStdout && $pipeIndex < count($pipeline) - 1 && $e->errorCode !== 'unsupported') {
                            $result = [
                                'ok' => false,
                                'stdout' => $e->getMessage(),
                                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'context' => $e->context],
                            ];
                        } elseif ($stderrMergeFile !== null && $pipeIndex < count($pipeline) - 1 && $e->errorCode !== 'unsupported') {
                            $this->writeRedirectedError($agent, $stderrMergeFile, $e->getMessage());
                            $result = [
                                'ok' => false,
                                'stdout' => '',
                                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'context' => $e->context],
                            ];
                        } else {
                            throw $e;
                        }
                    }
                    $input = $result['stdout'] ?? '';
                    $cwd = (string) ($result['cwd'] ?? $cwd);

                    if ($pipeIndex === count($pipeline) - 1 && trim((string) ($result['stdout'] ?? '')) !== '') {
                        $stdout = $stdout === ''
                            ? (string) ($result['stdout'] ?? '')
                            : rtrim($stdout)."\n".(string) ($result['stdout'] ?? '');
                    }
                }
                $lastOk = true;
            } catch (VfsError $e) {
                if ($this->hasUnsupportedStderrFileRedirect($redirections)) {
                    throw VfsError::unsupported('stderr file redirection is not supported; use 2>&1 to merge errors into stdout or 2>/dev/null to suppress them.');
                }

                $stderrMergedToStdout = $this->stderrMergedToStdout($redirections);
                $stderrMergeFile = $this->stderrMergeFile($redirections);
                if ($stderrMergedToStdout) {
                    $stdout = $stdout === ''
                        ? $e->getMessage()
                        : rtrim($stdout)."\n".$e->getMessage();
                }

                if ($e->errorCode === 'unsupported') {
                    if ($stderrMergedToStdout) {
                        throw new VfsError($e->errorCode, $e->getMessage(), $e->context + ['stdout' => $stdout]);
                    }

                    throw $e;
                }
                $lastOk = false;
                $stderrSuppressed = $this->stderrSuppressed($redirections);
                if ($stderrMergeFile !== null) {
                    $this->writeRedirectedError($agent, $stderrMergeFile, $e->getMessage());
                }
                if ($stderrSuppressed || $stderrMergedToStdout || $stderrMergeFile !== null || in_array($nextOperator, ['&&', '||', ';'], true)) {
                    $result = [
                        'ok' => false,
                        'stdout' => $stderrMergedToStdout ? $e->getMessage() : '',
                        'error' => ['code' => $e->errorCode, 'message' => $e->getMessage(), 'context' => $e->context],
                    ];

                    continue;
                }

                throw $e;
            }
        }

        if (is_array($result)) {
            $result['stdout'] = $stdout;
        }

        $ok = ! (is_array($result) && ($result['ok'] ?? true) === false);

        return [
            'ok' => $ok,
            'cwd' => $cwd,
            'stdout' => $stdout,
            'result' => $result,
        ];
    }
}
