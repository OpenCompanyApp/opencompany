<?php

namespace App\Agents\Tools\Vfs;

use App\Agents\Tools\Vfs\Concerns\HandlesVfsJson;
use App\Agents\Tools\Vfs\Contracts\ReturnsVfsEnvelope;
use App\Agents\Tools\Vfs\Support\VfsToolEnvelope;
use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsCommandHelp;
use App\Domain\Vfs\Core\VfsCommandSpecRegistry;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * `vfs_exec`: run one unix-like command line in the VFS.
 *
 * Owns the tool envelope around VfsCommandExecutor (Stream A owns command
 * semantics). Result contract, flat and compact:
 *
 *   {ok, exit_code, stdout, cwd, truncated?, next_cursor?, output_truncated?,
 *    stderr?, error?, data? (responseFormat=structured), diagnostics?}
 *
 * - stdout appears exactly once; executor `result` is never echoed whole.
 * - `error` is the only failure location: thrown errors and chained
 *   failures (`a && b`, `;`, `||`) both land there, never in `result.error`.
 * - Registry type stays `read` (Phase 1): CodeBridge classifies the effect
 *   per pipeline; mutating commands compose vfs_write inside the executor.
 *
 * Every call emits VfsOperationLog with a redacted command.
 */
class VfsExec implements ReturnsVfsEnvelope, Tool
{
    use HandlesVfsJson;

    public const TOOL_SLUG = 'vfs_exec';

    public function __construct(
        private readonly User $agent,
        private readonly VfsCommandExecutor $executor,
    ) {}

    public function handle(Request $request): string
    {
        $started = microtime(true);
        $ok = false;
        $errorCode = null;
        try {
            $input = $this->input($request);
            $command = trim($input->string('command'));
            if ($command === '') {
                throw VfsError::invalid('command is required, e.g. "ls /".', ['field' => 'command']);
            }
            $responseFormat = $input->choice('responseFormat');

            $result = $this->run($command, $input->string('cwd'), $input->budget(), $request->toArray(), $input->cursor());
            $payload = $this->envelope($result, $responseFormat === 'structured', $command);
            if ($input->bool('includeCommandSpec') && ($spec = $this->primaryCommandSpec($command)) !== null) {
                $payload['diagnostics'] = ['command_spec' => $spec];
            }
            $ok = (bool) $payload['ok'];
            $errorCode = $payload['error']['code'] ?? null;

            return VfsToolEnvelope::encode($payload);
        } catch (\Throwable $e) {
            $payload = VfsToolEnvelope::fromThrowable($e, self::TOOL_SLUG);
            // approval_required was already converted to the non-error
            // pending shape by the shared encoder; pass it through as-is.
            if (($payload['status'] ?? null) === 'pending_approval') {
                return VfsToolEnvelope::encode($payload);
            }
            $errorCode = $payload['error']['code'];
            // `2>&1` carries the merged output in the error context; surface
            // it once, at the top level, like a normal result.
            if (array_key_exists('stdout', $payload['error']['context'])) {
                $payload = ['ok' => false, 'exit_code' => $this->failureExitCode($errorCode), 'stdout' => (string) $payload['error']['context']['stdout']] + $payload;
                unset($payload['error']['context']['stdout']);
            } else {
                $payload = ['ok' => false, 'exit_code' => $this->failureExitCode($errorCode)] + $payload;
            }

            return VfsToolEnvelope::encode($payload);
        } finally {
            event(VfsOperationLog::forAgent(
                $this->agent,
                'tool',
                self::TOOL_SLUG,
                null,
                (string) ($request['cwd'] ?? '/'),
                $ok,
                $errorCode,
                (int) round((microtime(true) - $started) * 1000),
                ['command' => $this->redactCommandForLog((string) ($request['command'] ?? ''))],
            ));
        }
    }

    /**
     * Run the executor, forwarding `cursor` when the executor supports it.
     *
     * Integration seam (Stream A): VfsCommandExecutor::execute() gains an
     * optional trailing `?string $cursor` parameter in Phase 2. Until it
     * exists, a cursor fails loudly with `unsupported` instead of silently
     * returning the first page again. Once Stream A lands, replace this with
     * a direct named argument.
     *
     * @param  array<string, mixed>  $toolParameters
     * @return array<string, mixed>
     */
    private function run(string $command, string $cwd, VfsBudget $budget, array $toolParameters, ?string $cursor): array
    {
        if ($cursor === null) {
            return $this->executor->execute($this->agent, $command, $cwd, $budget, $toolParameters);
        }

        if (! $this->executorAcceptsCursor()) {
            throw VfsError::unsupported('cursor paging for vfs_exec is not available in this build. Narrow the path, raise maxEntries, or use vfs_read with cursor for paged files.', [
                'field' => 'cursor',
            ]);
        }

        /** @var array<string, mixed> */
        return (new \ReflectionMethod($this->executor, 'execute'))->invokeArgs($this->executor, [
            'agent' => $this->agent,
            'command' => $command,
            'cwd' => $cwd,
            'budget' => $budget,
            'toolParameters' => $toolParameters,
            'cursor' => $cursor,
        ]);
    }

    private function executorAcceptsCursor(): bool
    {
        foreach ((new \ReflectionMethod($this->executor, 'execute'))->getParameters() as $parameter) {
            if ($parameter->getName() === 'cursor') {
                return true;
            }
        }

        return false;
    }

    /**
     * Shape the executor result into the flat, stdout-once envelope.
     *
     * @param  array<string, mixed>  $result  {ok, cwd, stdout, result?, exit_code?, truncated?, next_cursor?, ...}
     * @return array<string, mixed>
     */
    private function envelope(array $result, bool $structured, string $command): array
    {
        $inner = is_array($result['result'] ?? null) ? $result['result'] : [];
        $ok = (bool) ($result['ok'] ?? true);
        $error = is_array($inner['error'] ?? null) ? $inner['error'] : (is_array($result['error'] ?? null) ? $result['error'] : null);

        $payload = [
            'ok' => $ok,
            'exit_code' => (int) ($result['exit_code'] ?? $inner['exit_code'] ?? ($ok ? 0 : 1)),
            'stdout' => (string) ($result['stdout'] ?? ''),
            'cwd' => (string) ($result['cwd'] ?? '/'),
        ];

        foreach (['truncated', 'next_cursor', 'output_truncated'] as $key) {
            $value = $result[$key] ?? $inner[$key] ?? null;
            if ($value !== null && $value !== false) {
                $payload[$key] = $value;
            }
        }

        // Truncation is never silent: say how to continue even when the
        // executor did not append its own truncation line to stdout.
        if (($payload['truncated'] ?? false) === true || ($payload['output_truncated'] ?? false) === true) {
            $payload['hint'] = isset($payload['next_cursor'])
                ? 'More output exists: repeat the same command with cursor=next_cursor, or narrow the path.'
                : 'Output was cut by a budget (maxEntries, maxBytes, maxMatches, maxFiles); raise it or narrow the path. A cut listing is not a complete count.';
        }

        $stderr = (string) ($result['stderr'] ?? $inner['stderr'] ?? '');
        if ($stderr !== '') {
            $payload['stderr'] = $stderr;
        }

        // A pending approval inside a command line is reported in the shared
        // non-error pending shape (see VfsToolEnvelope::error), keeping any
        // stdout produced before the gated command.
        if (! $ok && $error !== null && ($error['code'] ?? null) === 'approval_required') {
            $pending = VfsToolEnvelope::error('approval_required', (string) ($error['message'] ?? ''), is_array($error['context'] ?? null) ? $error['context'] : []);
            if (($pending['status'] ?? null) === 'pending_approval') {
                return $pending + ['stdout' => $payload['stdout'], 'cwd' => $payload['cwd']];
            }
        }

        if (! $ok && $error !== null) {
            $payload['error'] = [
                'code' => (string) ($error['code'] ?? 'invalid_request'),
                'message' => (string) ($error['message'] ?? 'Command failed.'),
                'context' => is_array($error['context'] ?? null) ? $error['context'] : [],
            ];
        }

        if ($structured) {
            $payload['data'] = $this->structuredData($inner, $payload['stdout'], $command);
        }

        return $payload;
    }

    private function failureExitCode(string $errorCode): int
    {
        // Usage-style failures exit 2 like POSIX tools; everything else 1.
        return in_array($errorCode, ['invalid_request', 'unsupported'], true) ? 2 : 1;
    }

    /**
     * @param  array<string, mixed>  $inner
     * @return array<string, mixed>|null
     */
    private function structuredData(array $inner, string $stdout, string $command): ?array
    {
        unset($inner['stdout'], $inner['error'], $inner['stderr']);
        $data = $inner;
        $spec = $this->primaryCommandSpec($command);
        $name = (string) ($spec['name'] ?? '');
        $lines = fn (): array => array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($stdout)) ?: [], fn (string $line): bool => $line !== ''));

        if ($name === 'stat') {
            $decoded = json_decode($stdout, true);
            if (is_array($decoded)) {
                $data['stat'] = $decoded;
            }
        } elseif (in_array($name, ['ls', 'find'], true)) {
            $data['paths'] = $lines();
        } elseif (in_array($name, ['grep', 'rg', 'zgrep'], true)) {
            $data['matches_text'] = $lines();
        }
        if ($spec !== null) {
            $data['command'] = $name;
        }

        return $data === [] ? null : $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primaryCommandSpec(string $command): ?array
    {
        if (preg_match('/^\s*([^\s|;&]+)/', $command, $matches) !== 1) {
            return null;
        }
        $canonical = VfsCommandSpecRegistry::canonical($matches[1]);
        if ($canonical === null) {
            return null;
        }

        return VfsCommandHelp::commandEntry($canonical);
    }

    private function redactCommandForLog(string $command): string
    {
        $redacted = preg_replace('/\b(authorization\s*:\s*(?:bearer|basic)\s+)[^\r\n,;\'"]+/i', '$1[redacted]', $command) ?? $command;
        $redacted = preg_replace('/\b(secret|token|auth|api[_-]?key|authorization|password|credential|bearer|cookie|session|client_secret|access_token)(\s*[:=]\s*)([^\s,&;\'"]+)/i', '$1$2[redacted]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b(bearer|basic)\s+([A-Za-z0-9._~+\/=-]+(?:\s+[A-Za-z0-9._~+\/=-]+)?)/i', '$1 [redacted]', $redacted) ?? $redacted;
        $redacted = preg_replace('/([?&](?:access_token|client_secret|secret|token|auth|api[_-]?key|authorization|password|credential|bearer|session)=)([^&\s\'"]+)/i', '$1[redacted]', $redacted) ?? $redacted;

        return preg_replace('/(sk-[A-Za-z0-9_-]{12,}|[A-Za-z0-9_\/+=-]{32,})/', '[redacted]', $redacted) ?? $redacted;
    }
}
