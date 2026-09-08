<?php

namespace App\Agents\Tools\Code;

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

            $this->lastExecutionMetadata = array_merge($result->toArray(), [
                'bridgeCalls' => $bridge?->getCallLog() ?? [],
            ]);

            return $this->formatForAgent($result);
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

    private function formatForAgent(CodeExecutionResult $result): string
    {
        $lines = [];

        if ($result->validatedOnly && $result->succeeded()) {
            $lines[] = 'Validation passed. No code was executed and no external calls were made.';
        }

        if ($result->output !== '') {
            $lines[] = "Console:\n{$result->output}";
        }

        if ($result->error !== null) {
            $location = $result->error['line'] !== null
                ? ' at line '.$result->error['line'].($result->error['column'] !== null ? ':'.$result->error['column'] : '')
                : '';
            $lines[] = 'Error ['.$result->error['type'].']'.$location.': '.$result->error['message'];
            $lines[] = 'Repair: '.$result->error['suggestion'];
            $lines[] = 'Retryable: '.($result->error['retryable'] ? 'yes' : 'no')
                .'; write effects: '.$result->error['effectStatus'].'.';
        }

        if ($result->result !== null) {
            $lines[] = 'Return value: '.$this->formatResult($result->result);
        }

        $lines[] = 'Execution ID: '.$result->executionId;
        $lines[] = "Runtime: {$result->executionTime}ms wall"
            .($result->cpuTime !== null ? ", {$result->cpuTime}ms Ruby CPU" : '');

        return implode("\n", $lines);
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
