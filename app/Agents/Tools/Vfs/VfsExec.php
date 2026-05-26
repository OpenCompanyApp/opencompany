<?php

namespace App\Agents\Tools\Vfs;

use App\Domain\Vfs\Core\VfsBudget;
use App\Domain\Vfs\Core\VfsCommandExecutor;
use App\Domain\Vfs\Core\VfsError;
use App\Domain\Vfs\Core\VfsOperationLog;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class VfsExec implements Tool
{
    public function __construct(
        private readonly User $agent,
        private readonly VfsCommandExecutor $executor,
    ) {}

    public function description(): string
    {
        return 'Execute a safe unix-like command inside the OpenCompany virtual filesystem. Supports read/navigation commands such as pwd, cd, ls, tree, find, rg, grep, cat, head, tail, wc, stat, file, du, jq, diff, and checksums. Also supports /files mutations such as mkdir, touch, tee, cp, mv, rm, and truncate when vfs_write permission allows them. This is not host shell access.';
    }

    public function handle(Request $request): string
    {
        $started = microtime(true);
        $ok = false;
        $errorCode = null;
        try {
            $command = trim((string) ($request['command'] ?? ''));
            if ($command === '') {
                $errorCode = 'invalid_request';

                return $this->encodeError('invalid_request', 'command is required.');
            }

            $result = $this->executor->execute(
                $this->agent,
                $command,
                (string) ($request['cwd'] ?? '/'),
                new VfsBudget(
                    maxEntries: $this->budgetInteger($request, 'maxEntries', 100, 1),
                    maxDepth: $this->budgetInteger($request, 'maxDepth', 3, 0),
                    maxBytes: $this->budgetInteger($request, 'maxBytes', 100000, 1),
                    maxFiles: $this->budgetInteger($request, 'maxFiles', 100, 1),
                    maxMatches: $this->budgetInteger($request, 'maxMatches', 100, 1),
                ),
            );
            $ok = (bool) ($result['ok'] ?? true);
            $result['command'] = $command;
            $result['exit_code'] = $ok ? 0 : 1;
            $result['stderr'] = (string) ($result['result']['stderr'] ?? '');
            $result['data'] = $result['result'] ?? null;
            $result['diagnostics'] = [];

            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (VfsError $e) {
            $errorCode = $e->errorCode;

            return $this->encodeError($e->errorCode, $e->getMessage(), $e->context);
        } catch (\Throwable $e) {
            $errorCode = 'internal_error';

            return $this->encodeError('internal_error', $e->getMessage());
        } finally {
            event(VfsOperationLog::forAgent(
                $this->agent,
                'tool',
                'vfs_exec',
                null,
                (string) ($request['cwd'] ?? '/'),
                $ok,
                $errorCode,
                (int) round((microtime(true) - $started) * 1000),
                ['command' => $this->redactCommandForLog((string) ($request['command'] ?? ''))],
            ));
        }
    }

    private function budgetInteger(Request $request, string $key, int $default, int $min): int
    {
        if (! isset($request[$key])) {
            return $default;
        }

        $raw = $request[$key];
        if (! is_int($raw) && (! is_string($raw) || preg_match('/^\d+$/', $raw) !== 1)) {
            throw VfsError::invalid("{$key} must be an integer.", ['field' => $key]);
        }

        $value = (int) $raw;
        if ($value < $min) {
            throw VfsError::invalid("{$key} must be at least {$min}.", ['field' => $key, 'min' => $min]);
        }

        return $value;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()->required()->description('Unix-like VFS command, e.g. `ls /docs`, `rg "refund" /docs`, `cat /tasks/counts.json`, or `/files` mutations like `mkdir /files/tmp` when vfs_write is allowed.'),
            'cwd' => $schema->string()->description('Virtual working directory. Defaults to /.'),
            'maxEntries' => $schema->integer()->description('Maximum entries returned by list-like commands. Default: 100.'),
            'maxDepth' => $schema->integer()->description('Maximum recursion depth for tree/find/search. Default: 3.'),
            'maxBytes' => $schema->integer()->description('Maximum bytes read from a single virtual file. Default: 100000.'),
            'maxFiles' => $schema->integer()->description('Maximum files scanned by search commands. Default: 100.'),
            'maxMatches' => $schema->integer()->description('Maximum matches returned by search commands. Default: 100.'),
        ];
    }

    private function encodeError(string $code, string $message, array $context = []): string
    {
        $payload = [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'context' => $context,
            ],
        ];
        if (array_key_exists('stdout', $context)) {
            $payload['stdout'] = (string) $context['stdout'];
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
