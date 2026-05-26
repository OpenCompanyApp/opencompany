<?php

namespace App\Domain\Vfs\Core;

use App\Domain\Vfs\Core\Concerns\ExecutesVfsBrowseCommands;
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
use App\Models\User;
use App\Services\AgentPermissionService;

/**
 * Safe unix-like command interpreter for the virtual filesystem.
 *
 * This class owns orchestration only: command chaining, pipes, cwd movement, and
 * dispatch. The command families live in focused traits so future VFS work can
 * extend a narrow surface without turning the executor back into a god file.
 */
class VfsCommandExecutor
{
    use ExecutesVfsBrowseCommands;
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
     * @return array<string, array{class: string, description: string}>
     */
    public static function commandCatalog(): array
    {
        return VfsCommandCatalog::all();
    }

    public function __construct(
        private readonly OpenCompanyVfs $vfs,
        private readonly AgentPermissionService $permissions,
    ) {}

    private ?User $currentAgent = null;

    public function execute(User $agent, string $command, string $cwd = '/', ?VfsBudget $budget = null): array
    {
        $this->currentAgent = $agent;
        $budget ??= new VfsBudget;
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
                    try {
                        $result = $this->runCommand($agent, $tokens, $cwd, $budget, $input);
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

                if ($stderrMergedToStdout) {
                    throw new VfsError($e->errorCode, $e->getMessage(), $e->context + ['stdout' => $stdout]);
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
