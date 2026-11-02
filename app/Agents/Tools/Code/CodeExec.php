<?php

namespace App\Agents\Tools\Code;

use App\Agents\Support\UntrustedContent;
use App\Agents\Tools\ToolRegistry;
use App\Models\Task;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\CodeExecutionResult;
use App\Services\MrubySandboxService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Compiles or executes synchronous Ruby with permission-scoped app.* APIs.
 *
 * The model receives concise human-readable output. Rich telemetry is exposed
 * through lastExecutionMetadata() to the synchronous ToolInvoked listener, so
 * implementation markers never pollute model context or console output.
 */
final class CodeExec implements Tool
{
    /** @var array<string, mixed>|null */
    private ?array $lastExecutionMetadata = null;

    public function __construct(
        private MrubySandboxService $sandbox,
        private ToolRegistry $registry,
        private CodeApiDocGenerator $docGenerator,
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Validate or execute bounded Ruby with permission-scoped app.* capabilities. Inspect code_read_doc first. Use keyword arguments, bounded reads, puts for logs, and the final expression as the result. Host records support string or symbol []/fetch access and are frozen. Never blindly repeat succeeded or unknown write effects. Gems, require, eval, ambient filesystem/network/process/environment access and unmanaged concurrency are unavailable.';
    }

    public function handle(Request $request): string
    {
        $this->lastExecutionMetadata = null;

        if (! isset($request['code']) || ! is_string($request['code']) || trim($request['code']) === '') {
            return 'Missing required parameter "code". Provide a Ruby script to validate or execute.';
        }

        $mode = (string) ($request['mode'] ?? 'execute');
        if (! in_array($mode, ['validate', 'execute'], true)) {
            return 'Invalid "mode". Use "validate" or "execute".';
        }

        try {
            $taskId = $this->registry->getTaskContext();
            $cancelled = $taskId === null ? null : fn (): bool => ! Task::query()
                ->whereKey($taskId)->where('workspace_id', $this->agent->workspace_id)
                ->whereNotIn('status', [Task::STATUS_CANCELLED, Task::STATUS_FAILED, Task::STATUS_COMPLETED])->exists();
            $bridge = $mode === 'execute'
                ? new CodeBridge(
                    $this->agent,
                    $this->registry,
                    $this->docGenerator,
                    $this->sandbox->profile('agent'),
                )
                : null;

            $result = $this->sandbox->execute(
                code: $request['code'],
                profile: 'agent',
                bridge: $bridge,
                validateOnly: $mode === 'validate',
                sourceName: 'agent-code.rb',
                cancelled: $cancelled,
            );

            $callLog = $bridge?->getCallLog() ?? [];
            $this->lastExecutionMetadata = array_merge($result->toArray(), [
                'bridgeCalls' => $callLog,
            ]);

            return $this->formatForAgent($result, $this->untrustedOrigins($callLog));
        } catch (\Throwable) {
            // Catalog/bootstrap failures can contain service paths or provider
            // configuration. Only typed sandbox diagnostics are model-visible.
            return 'Code execution could not start. Ask an operator to verify the Ruby engine and capability configuration.';
        }
    }

    /**
     * Side-channel metadata consumed synchronously by CheckpointToolCall.
     *
     * @return array<string, mixed>|null
     */
    public function lastExecutionMetadata(): ?array
    {
        return $this->lastExecutionMetadata;
    }

    /**
     * Render the model-visible summary of one execution.
     *
     * When $untrustedOrigins is non-empty, script-derived text (console output,
     * the error message, and the return value) can contain third-party content,
     * so it is emitted inside one UntrustedContent block. Host-authored guidance
     * (repair hint, retry/effect status, execution ID, timing) stays outside the
     * block so the model can still trust it. With no untrusted callbacks the
     * output format is unchanged.
     *
     * @param  list<string>  $untrustedOrigins
     */
    private function formatForAgent(CodeExecutionResult $result, array $untrustedOrigins = []): string
    {
        $lines = [];
        $dataLines = [];
        $hostLines = [];

        if ($result->validatedOnly && $result->succeeded()) {
            $lines[] = 'Validation passed (Ruby syntax/profile only). No code was executed and no external calls were made. Capability names, argument schemas and current permissions are checked at execution time.';
        }

        if ($result->output !== '') {
            $dataLines[] = "Console:\n{$result->output}";
        }

        if ($result->error !== null) {
            $location = $result->error['line'] !== null
                ? ' at line '.$result->error['line'].($result->error['column'] !== null ? ':'.$result->error['column'] : '')
                : '';
            $dataLines[] = 'Error ['.$result->error['type'].']'.$location.': '.$result->error['message'];
            $hostLines[] = 'Repair: '.$result->error['suggestion'];
            $hostLines[] = 'Retryable: '.($result->error['retryable'] ? 'yes' : 'no')
                .'; write effects: '.$result->error['effectStatus'].'.';
        }

        $returnLine = $result->result !== null
            ? 'Return value: '.$this->formatResult($result->result)
            : null;

        if ($untrustedOrigins !== [] && ($dataLines !== [] || $returnLine !== null)) {
            $block = implode("\n", array_filter([...$dataLines, $returnLine], fn ($line) => $line !== null));
            $lines[] = UntrustedContent::wrap($block, 'code_exec', implode(', ', $untrustedOrigins));
            array_push($lines, ...$hostLines);
        } else {
            // Preserve the historical ordering exactly when nothing untrusted
            // ran: console, error, repair/retry guidance, return value.
            array_push($lines, ...$dataLines, ...$hostLines);
            if ($returnLine !== null) {
                $lines[] = $returnLine;
            }
        }

        $lines[] = 'Execution ID: '.$result->executionId;
        $lines[] = "Runtime: {$result->executionTime}ms wall"
            .($result->cpuTime !== null ? ", {$result->cpuTime}ms Ruby CPU" : '');

        return implode("\n", $lines);
    }

    /**
     * Collect the Code Mode namespaces whose callbacks returned third-party data.
     *
     * Untrusted sources: every `integrations.*` namespace (installed integration
     * packages), every `mcp.*` namespace (remote MCP servers), and the built-in
     * `web` group. Callbacks refused before dispatch (denied,
     * pending approval, unknown function, argument errors) never reached the
     * source, so they do not taint the output. Internal workspace namespaces
     * (tasks, docs, tables, vfs, ...) are trusted workspace data.
     *
     * @param  list<array<string, mixed>>  $callLog
     * @return list<string>
     */
    private function untrustedOrigins(array $callLog): array
    {
        $origins = [];

        foreach ($callLog as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $group = (string) ($entry['group'] ?? '');
            $reachedSource = ($entry['status'] ?? null) === 'ok'
                || ($entry['errorType'] ?? null) === 'tool_error';

            if (! $reachedSource) {
                continue;
            }

            if (str_starts_with($path, 'mcp.')) {
                // mcp.<server>.<tool>: name the server, not just "mcp".
                $origins[] = implode('.', array_slice(explode('.', $path), 0, 2));
            } elseif (str_starts_with($path, 'integrations.') || str_starts_with($group, 'mcp_')) {
                $origins[] = $group !== '' ? $group : $path;
            } elseif ($group === 'web' || str_starts_with($path, 'web.')) {
                $origins[] = 'web';
            }
        }

        return array_values(array_unique($origins));
    }

    private function formatResult(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return (string) json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema
                ->string()
                ->description('Bounded Ruby script. The final expression is returned; use puts for logs. No imports, gems or ambient I/O.')
                ->required(),
            'mode' => $schema
                ->string()
                ->description('"validate" compiles without running or exposing app.*; "execute" runs under the fixed agent profile. Default: execute.'),
        ];
    }
}
