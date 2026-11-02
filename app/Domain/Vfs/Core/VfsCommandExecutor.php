<?php

namespace App\Domain\Vfs\Core;

use App\Domain\Vfs\Core\Concerns\ExecutesVfsArchiveCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsBinaryInspectionCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsComparisonTransforms;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsDiscoveryCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsFieldTransforms;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsFindCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsListCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsMetadataCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsMutationCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsOutputTransforms;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsPathCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsReadCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsSearchCommands;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsStructuredTransforms;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsTextFilters;
use App\Domain\Vfs\Core\Concerns\ExecutesVfsTreeCommands;
use App\Domain\Vfs\Core\Concerns\ReadsVfsCommandInputs;
use App\Domain\Vfs\Core\Concerns\ResolvesVfsRedirectionEffects;
use App\Domain\Vfs\Core\Concerns\RoutesVfsCommands;
use App\Domain\Vfs\OpenCompany\OpenCompanyVfs;
use App\Domain\Vfs\OpenCompany\Policy\VfsMutationPolicy;
use App\Models\User;

/**
 * Safe unix-like command interpreter for the virtual filesystem.
 *
 * Owns orchestration only: parsing (VfsShellLexer), `;`/newline/`&&`/`||`
 * lists, pipes, per-command exit status, stdin/stdout/stderr plumbing,
 * redirections, the aggregate output cap, cursors, and the result envelope.
 * Command families live in focused traits under Concerns; pure engines
 * (sed, awk, jq, diff, printf) live in their own Core classes.
 *
 * Does not own: storage, authorization, or policy. Every read goes through
 * OpenCompanyVfs; every write passes ExecutesVfsMutationCommands::
 * assertWriteCommandAllowed (vfs_exec + vfs_write) or the governed
 * OpenCompanyVfs::patch path before it reaches the facade.
 *
 * Result contract of execute() (Stream B's vfs_exec renders it):
 *
 *   ok               true unless an error envelope is thrown (see below)
 *   exit_code        status of the last executed pipeline: 0 success,
 *                    1 "false"/no match/differences, 2 error (POSIX-like)
 *   stdout           the only copy of the output (`result` never repeats it)
 *   stderr           messages of errors that did not end the command line,
 *                    plus warnings (truncation notes for piped output)
 *   cwd              cwd after `cd`
 *   class            spec class of the last executed command
 *   truncated        any command reported budget-truncated results
 *   next_cursor      continuation cursor of the first pageable command
 *                    (ls DIR, cat PAGED_FILE, find); pass back as `cursor`
 *   output_truncated stdout exceeded VfsBudget::$maxOutputBytes
 *   result           command-specific metadata of the last command (counts,
 *                    limits, written paths); never stdout or top-level keys
 *
 * Error envelope (VfsError thrown):
 * - the last executed command failed and its stderr was not redirected;
 *   context gains `stdout` (earlier output, preserved), `stderr`, and
 *   `exit_code` 2;
 * - `approval_required` and `unsupported` abort the whole line immediately,
 *   never swallowed by `||` or `;`; context gains `stdout`/`stderr` and, for
 *   approvals, `status: "pending_approval"`;
 * - binary or non-UTF-8 stdout fails with invalid_request and a hint.
 */
class VfsCommandExecutor
{
    use ExecutesVfsArchiveCommands;
    use ExecutesVfsBinaryInspectionCommands;
    use ExecutesVfsComparisonTransforms;
    use ExecutesVfsDiscoveryCommands;
    use ExecutesVfsFieldTransforms;
    use ExecutesVfsFindCommands;
    use ExecutesVfsListCommands;
    use ExecutesVfsMetadataCommands;
    use ExecutesVfsMutationCommands;
    use ExecutesVfsOutputTransforms;
    use ExecutesVfsPathCommands;
    use ExecutesVfsReadCommands;
    use ExecutesVfsSearchCommands;
    use ExecutesVfsStructuredTransforms;
    use ExecutesVfsTextFilters;
    use ExecutesVfsTreeCommands;
    use ReadsVfsCommandInputs;
    use ResolvesVfsRedirectionEffects;
    use RoutesVfsCommands;

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

    /** Cursor supplied by the caller; consumed by the first pageable command. */
    private ?string $pendingCursor = null;

    private ?string $nextCursor = null;

    private bool $anyTruncated = false;

    private string $stderr = '';

    private int $lastStatus = 0;

    /** Whether the command now running writes its stdout straight to the agent. */
    private bool $displayingStdout = false;

    /** @var list<bool> Literal mask for the argv of the command now running (without its name). */
    private array $argLiteral = [];

    /** @var list<bool> Glob mask for the argv of the command now running (without its name). */
    private array $argGlob = [];

    private string $displayed = '';

    private int $displayedBytes = 0;

    private int $droppedBytes = 0;

    /**
     * Classify a command line's effect without executing it: 'write' when any
     * command in any group or pipeline can mutate, else 'read'.
     *
     * Used by Code Mode (CodeBridge) to give `app.vfs.shell(...)` a durable
     * write receipt and a `write` effect in the call ledger: the registry
     * types vfs_exec as a read because most commands are. A command counts
     * as a write when its spec class is write, destructive, or binary-archive
     * (archive list modes included, conservatively), when it declares
     * `writes`, when it is `sed -i`, when xargs could run a writing command, or
     * when stdout/stderr is redirected to a file. Anything that fails to parse
     * is a write: an unclassifiable command must never be logged as a
     * retryable read.
     *
     * Pure: no agent, workspace, or storage access.
     *
     * @return 'read'|'write'
     */
    public function classifyEffect(string $command): string
    {
        try {
            foreach (VfsShellLexer::parse($command) as $group) {
                foreach ($group['pipeline'] as $segment) {
                    foreach ($segment->redirections as $redirect) {
                        if ($redirect['stream'] !== 'stdin' && $redirect['mode'] !== 'merge' && $redirect['path'] !== '/dev/null') {
                            return 'write';
                        }
                    }

                    $name = (string) $segment->name();
                    // xargs runs its argv as a command; any writing command
                    // name among its tokens makes the whole call a write.
                    $candidates = VfsCommandSpecRegistry::canonical($name) === 'xargs' ? $segment->words : [$name];
                    foreach ($candidates as $candidate) {
                        $spec = VfsCommandSpecRegistry::get((string) $candidate);
                        if ($spec !== null && (in_array($spec->class, ['write', 'destructive', 'binary-archive'], true) || $spec->writes !== null)) {
                            return 'write';
                        }
                    }
                    if (VfsCommandSpecRegistry::canonical($name) === 'sed' && $this->sedInPlaceRequested(array_slice($segment->words, 1), array_slice($segment->literal, 1))) {
                        return 'write';
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
     * Execute one command line. See the class docblock for the result and
     * error contract.
     *
     * @param  array<string, mixed>|null  $toolParameters  Original tool call parameters; approval requests replay them.
     * @param  string|null  $cursor  Continuation cursor from a previous result's next_cursor.
     * @return array<string, mixed>
     *
     * @throws VfsError
     */
    public function execute(User $agent, string $command, string $cwd = '/', ?VfsBudget $budget = null, ?array $toolParameters = null, ?string $cursor = null): array
    {
        $this->currentAgent = $agent;
        $this->currentCommand = $command;
        $this->currentToolParameters = $toolParameters ?? ['command' => $command, 'cwd' => $cwd];
        // Invariant: the executor evaluates mutation policy (and may create
        // approvals) before it reaches the facade, so it must check the
        // workspace binding itself.
        $this->vfs->assertBoundWorkspace($agent);
        $budget ??= new VfsBudget;
        $this->currentBudget = $budget;
        $this->pendingCursor = $cursor === '' ? null : $cursor;
        $this->nextCursor = null;
        $this->anyTruncated = false;
        $this->stderr = '';
        $this->lastStatus = 0;
        $this->displayed = '';
        $this->displayedBytes = 0;
        $this->droppedBytes = 0;
        $cwd = $this->vfs->normalizePath($cwd);

        $groups = VfsShellLexer::parse($command);
        // Invariant: every command and flag in the line is validated before
        // anything runs, including branches && / || may skip, so an
        // unsupported flag can never hide behind a condition or follow a write.
        $this->preflight($groups);

        $status = 0;
        $pendingError = null;
        $lastResult = null;
        $lastClass = 'browse';
        try {
            foreach ($groups as $group) {
                if (($group['operator'] === '&&' && $status !== 0) || ($group['operator'] === '||' && $status === 0)) {
                    continue;
                }
                if ($pendingError !== null) {
                    // The failed command did not end the line: its message
                    // becomes stderr instead of an error envelope.
                    $this->appendStderr($pendingError->getMessage());
                    $pendingError = null;
                }

                $run = $this->runPipeline($agent, $group['pipeline'], $cwd, $budget);
                $status = $run['status'];
                $pendingError = $run['error'];
                $lastResult = $run['result'];
                $lastClass = (string) ($lastResult['class'] ?? $lastClass);
                $this->appendDisplay($run['stdout']);
                $this->lastStatus = $status;
            }
        } catch (VfsError $e) {
            throw $this->withOutputContext($e, $e->errorCode === 'approval_required' ? null : 2);
        }

        if ($pendingError !== null) {
            throw $this->withOutputContext($pendingError, 2);
        }

        $stdout = $this->finalStdout();
        $this->assertDisplayableOutput($stdout);

        $metadata = is_array($lastResult) ? $lastResult : [];
        // result carries only command-specific metadata: everything the
        // envelope already has at the top level is removed, stdout above all.
        unset($metadata['stdout'], $metadata['stderr'], $metadata['error'], $metadata['next_cursor'], $metadata['exit_code'], $metadata['cwd'], $metadata['class']);

        return [
            'ok' => true,
            'exit_code' => $status,
            'cwd' => $cwd,
            'class' => $lastClass,
            'stdout' => $stdout,
            'stderr' => $this->stderr,
            'truncated' => $this->anyTruncated,
            'next_cursor' => $this->nextCursor,
            'output_truncated' => $this->droppedBytes > 0,
            'result' => $metadata,
        ];
    }

    /**
     * Run one pipeline. Each command's stdout feeds the next command's stdin;
     * the last command's stdout is returned for display. A failing command
     * inside the pipeline writes its message to stderr and passes empty stdout
     * on, like bash; only the last command decides the status.
     *
     * @param  list<VfsShellCommand>  $pipeline
     * @return array{status: int, error: VfsError|null, stdout: string, result: array<string, mixed>|null}
     */
    private function runPipeline(User $agent, array $pipeline, string &$cwd, VfsBudget $budget): array
    {
        $input = null;
        $status = 0;
        $error = null;
        $result = null;
        $last = count($pipeline) - 1;

        foreach ($pipeline as $index => $segment) {
            $words = array_map(fn (string $word): string => str_replace(VfsShellLexer::STATUS_PLACEHOLDER, (string) $this->lastStatus, $word), $segment->words);
            $redirections = $this->normalizedRedirections($segment->redirections, $cwd);
            $stdin = $input;
            if ($segment->stdin !== null) {
                $stdin = $segment->stdin;
            }
            if (($stdinPath = $this->stdinRedirectPath($redirections)) !== null) {
                $stdin = $stdinPath === '/dev/null' ? '' : null;
            }
            $this->displayingStdout = $index === $last && $this->stdoutReachesDisplay($redirections);

            $error = null;
            try {
                if ($stdinPath !== null && $stdinPath !== '/dev/null') {
                    $stdin = $this->readFull($agent, $stdinPath);
                }
                $this->argLiteral = array_slice($segment->literal, 1);
                $this->argGlob = array_slice($segment->glob, 1);
                $result = $this->runCommand($agent, $words, $cwd, $budget, $stdin);
                $status = (int) ($result['exit_code'] ?? 0);
            } catch (VfsError $e) {
                if (in_array($e->errorCode, ['approval_required', 'unsupported'], true)) {
                    throw $e;
                }
                $error = $e;
                $status = 2;
                $result = ['stdout' => '', 'class' => VfsCommandSpecRegistry::get((string) ($words[0] ?? ''))->class ?? 'browse'];
            }
            if ($segment->negate) {
                $status = $status === 0 ? 1 : 0;
            }
            if (! empty($result['truncated'])) {
                $this->anyTruncated = true;
            }

            $streams = $this->applyRedirections($agent, $result, $redirections, $error);
            if ($streams['error_handled']) {
                $error = null;
            } elseif ($error !== null && $index < $last) {
                // A failure mid-pipeline does not end the pipeline.
                $this->appendStderr($error->getMessage());
                $error = null;
            }
            if ($streams['stderr'] !== '') {
                $this->appendStderr($streams['stderr']);
            }
            if ($index < $last) {
                $this->assertPipeWithinBudget($streams['stdout']);
            }
            $input = $streams['stdout'];
            $cwd = (string) ($result['cwd'] ?? $cwd);
        }

        return ['status' => $status, 'error' => $error, 'stdout' => (string) $input, 'result' => $result];
    }

    /**
     * Add one pipeline's output to the displayed stdout, enforcing the
     * aggregate cap. A newline separates outputs of consecutive commands when
     * the previous one did not end with one (display-only convenience; pipes
     * and redirects always carry exact bytes).
     */
    private function appendDisplay(string $output): void
    {
        if ($output === '') {
            return;
        }
        if ($this->displayed !== '' && ! str_ends_with($this->displayed, "\n")) {
            $output = "\n".$output;
        }
        // An explicit maxOutputBytes is the cap. Otherwise a caller that
        // raised maxBytes for one big read gets at least that much, plus
        // headroom so a full read's own truncation line survives.
        $budget = $this->currentBudget ?? new VfsBudget;
        $cap = $budget->outputCapExplicit ? $budget->maxOutputBytes : max($budget->maxOutputBytes, $budget->maxBytes + 1_024);
        $room = $cap - $this->displayedBytes;
        if ($this->droppedBytes > 0 || strlen($output) > $room) {
            $kept = $room > 0 ? mb_strcut($output, 0, $room, 'UTF-8') : '';
            $cut = strrpos($kept, "\n");
            if ($cut !== false && $cut > 0) {
                $kept = substr($kept, 0, $cut + 1);
            }
            $this->displayed .= $kept;
            $this->displayedBytes += strlen($kept);
            $this->droppedBytes += strlen($output) - strlen($kept);

            return;
        }
        $this->displayed .= $output;
        $this->displayedBytes += strlen($output);
    }

    private function finalStdout(): string
    {
        if ($this->droppedBytes === 0) {
            return $this->displayed;
        }
        $total = $this->displayedBytes + $this->droppedBytes;
        $separator = $this->displayed === '' || str_ends_with($this->displayed, "\n") ? '' : "\n";

        return $this->displayed.$separator.VfsText::outputTruncationLine($this->displayedBytes, $total)."\n";
    }

    private function appendStderr(string $message): void
    {
        if ($message === '') {
            return;
        }
        $this->stderr .= rtrim($message, "\n")."\n";
    }

    /**
     * Fail loudly instead of returning an empty or corrupted tool result when
     * stdout is binary (gzip, tar without -f, xxd -r) or not valid UTF-8.
     */
    private function assertDisplayableOutput(string $stdout): void
    {
        if (mb_check_encoding($stdout, 'UTF-8') && ! str_contains(rtrim($stdout, "\0"), "\0") || $stdout === '') {
            return;
        }
        if (mb_check_encoding($stdout, 'UTF-8') && preg_match('/^[^\0]*(\0[^\0]*)+$/', $stdout) === 1 && preg_match('/[\x01-\x08\x0E-\x1F]/', $stdout) !== 1) {
            // NUL-separated text (find -print0, shuf -z) is displayable.
            return;
        }

        throw VfsError::invalid('Command output is binary or not valid UTF-8 text ('.strlen($stdout).' bytes), so it cannot be shown.', [
            'bytes' => strlen($stdout),
            'hint' => 'Redirect it to a /files path (> /files/out.bin), or view it with base64 or xxd (e.g. ... | base64, xxd -l 256 file).',
            'stdout' => '',
        ]);
    }

    /**
     * Intermediate pipe data is bounded by the content ceiling so `xargs cat`
     * or a huge `cat` cannot exhaust memory; it fails loudly rather than
     * silently passing a prefix on.
     */
    private function assertPipeWithinBudget(string $data): void
    {
        $ceiling = $this->contentCeiling();
        if (strlen($data) > $ceiling) {
            throw VfsError::invalid('Pipeline data exceeded the VFS content limit ('.$ceiling.' bytes).', [
                'bytes' => strlen($data),
                'max_content_bytes' => $ceiling,
                'hint' => 'Filter earlier in the pipeline (grep, head, sed -n) or process fewer files per command.',
            ]);
        }
    }

    /**
     * Rebuild an error with the line's output so far, so earlier output is
     * never lost when a later command fails or an approval is requested.
     */
    private function withOutputContext(VfsError $error, ?int $exitCode): VfsError
    {
        $stdout = $this->finalStdout();
        if (! mb_check_encoding($stdout, 'UTF-8')) {
            $stdout = '';
        }
        $context = $error->context;
        if ($stdout !== '' || array_key_exists('stdout', $context)) {
            $context['stdout'] = $stdout !== '' ? $stdout : (string) ($context['stdout'] ?? '');
        }
        if ($this->stderr !== '') {
            $context['stderr'] = $this->stderr;
        }
        if ($error->errorCode === 'approval_required') {
            $context['status'] = 'pending_approval';
        } elseif ($exitCode !== null) {
            $context['exit_code'] = $exitCode;
        }
        if ($this->nextCursor !== null) {
            $context['next_cursor'] = $this->nextCursor;
        }

        return new VfsError($error->errorCode, $error->getMessage(), $context);
    }
}
