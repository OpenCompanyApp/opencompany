<?php

namespace App\Agents\Tools\Code;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use App\Services\CodeBridge;
use App\Services\CodeExecutionResult;
use App\Services\QuickJsSandboxService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Compiles or executes synchronous JavaScript with permission-scoped app.* APIs.
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
        private QuickJsSandboxService $sandbox,
        private ToolRegistry $registry,
        private CodeApiDocGenerator $docGenerator,
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Validate or execute synchronous JavaScript with permission-scoped app.* capabilities. First inspect the relevant namespace with code_read_doc. Prefer one named object argument, page large reads, use console.log/info/warn/error (print/dump are aliases), and never blindly retry an execution whose error reports succeeded or unknown write effects. Modules, Promise jobs, filesystem, network, process, and environment access are unavailable.';
    }

    public function handle(Request $request): string
    {
        $this->lastExecutionMetadata = null;

        if (! isset($request['code']) || ! is_string($request['code']) || trim($request['code']) === '') {
            return 'Missing required parameter "code". Provide a JavaScript function body to validate or execute.';
        }

        $mode = (string) ($request['mode'] ?? 'execute');
        if (! in_array($mode, ['validate', 'execute'], true)) {
            return 'Invalid "mode". Use "validate" or "execute".';
        }

        try {
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
                sourceName: 'agent-code.js',
            );

            $this->lastExecutionMetadata = array_merge($result->toArray(), [
                'bridgeCalls' => $bridge?->getCallLog() ?? [],
            ]);

            return $this->formatForAgent($result);
        } catch (\Throwable $exception) {
            return 'Code execution could not start: '.$exception->getMessage();
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
        $lines[] = "Runtime: {$result->executionTime}ms wall, {$result->cpuTime}ms JS CPU";

        return implode("\n", $lines);
    }

    private function formatResult(mixed $value): string
    {
        if (is_array($value)) {
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
                ->description('Synchronous JavaScript function body. Top-level return is supported.')
                ->required(),
            'mode' => $schema
                ->string()
                ->description('"validate" compiles without running or exposing app.*; "execute" runs under the fixed agent profile. Default: execute.'),
        ];
    }
}
