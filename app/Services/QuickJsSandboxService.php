<?php

namespace App\Services;

use Illuminate\Support\Str;
use OpenCompany\IntegrationCore\Script\ScriptBridgeException;
use QuickJS\CallbackException;
use QuickJS\ConversionException;
use QuickJS\Exception as QuickJsException;
use QuickJS\MemoryException;
use QuickJS\RuntimeException as QuickJsRuntimeException;
use QuickJS\Sandbox;
use QuickJS\StackException;
use QuickJS\SyntaxException;
use QuickJS\TimeoutException;

/**
 * Executes untrusted synchronous JavaScript in the native QuickJS sandbox.
 *
 * The runtime begins capability-empty. This service injects data globals,
 * installs bounded console helpers, and—only when supplied—exposes app.* via a
 * private PHP callback captured by a trusted bootstrap. The callback global is
 * deleted before user code runs. No filesystem, network, process, module,
 * Promise-job, environment, or bytecode authority is added here.
 */
final class QuickJsSandboxService
{
    /**
     * Compile or execute one JavaScript function body under a fixed host profile.
     *
     * @param  array<string, mixed>  $globals  JSON-compatible data such as automation ctx
     */
    public function execute(
        string $code,
        string $profile = 'agent',
        ?CodeBridge $bridge = null,
        array $globals = [],
        bool $validateOnly = false,
        string $sourceName = 'opencompany-code.js',
    ): CodeExecutionResult {
        $limits = $this->profile($profile);
        $executionId = (string) Str::uuid();
        $logs = [];
        $outputBytes = 0;
        $outputTruncated = false;
        $sandbox = null;
        $start = microtime(true);

        try {
            $sourceLimit = (int) $limits['source_limit'];
            if (strlen($code) > $sourceLimit) {
                throw new CodeRuntimeException(
                    'source_too_large',
                    "JavaScript source exceeds the {$sourceLimit}-byte profile limit.",
                );
            }

            $sandbox = new Sandbox(
                memory_limit: (int) $limits['memory_limit'],
                cpu_limit: (float) $limits['cpu_limit'],
                stack_limit: (int) $limits['stack_limit'],
            );

            foreach ($globals as $name => $value) {
                if (! preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $name)
                    || str_starts_with($name, '__')) {
                    throw new CodeRuntimeException(
                        'invalid_global',
                        "Invalid or reserved JavaScript global name: {$name}",
                    );
                }

                $sandbox->setGlobal($name, $value);
            }

            if (! $validateOnly) {
                $this->installHostBootstrap(
                    $sandbox,
                    $bridge,
                    $logs,
                    $outputBytes,
                    $outputTruncated,
                    (int) $limits['output_limit'],
                    (int) $limits['log_limit'],
                );
            }

            $script = $sandbox->load($code, $sourceName);
            $result = $validateOnly ? null : $script();

            if (! $validateOnly) {
                $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($encoded !== false && strlen($encoded) > (int) $limits['result_limit']) {
                    throw new CodeRuntimeException(
                        'result_too_large',
                        'The returned value is too large. Return a smaller summary and page large capability results.',
                    );
                }
            }

            return new CodeExecutionResult(
                executionId: $executionId,
                profile: $profile,
                output: $this->renderOutput($logs),
                logs: $logs,
                error: null,
                result: $result,
                executionTime: round((microtime(true) - $start) * 1000, 1),
                cpuTime: $this->cpuUsage($sandbox),
                memoryUsage: $this->memoryUsage($sandbox),
                peakMemoryUsage: $this->peakMemoryUsage($sandbox),
                effects: $bridge?->effectSummary() ?? $this->emptyEffects(),
                validatedOnly: $validateOnly,
                outputTruncated: $outputTruncated,
            );
        } catch (\Throwable $exception) {
            $effects = $bridge?->effectSummary() ?? $this->emptyEffects();

            return new CodeExecutionResult(
                executionId: $executionId,
                profile: $profile,
                output: $this->renderOutput($logs),
                logs: $logs,
                error: $this->describeError($exception, $sourceName, $effects),
                result: null,
                executionTime: round((microtime(true) - $start) * 1000, 1),
                cpuTime: $this->cpuUsage($sandbox),
                memoryUsage: $this->memoryUsage($sandbox),
                peakMemoryUsage: $this->peakMemoryUsage($sandbox),
                effects: $effects,
                validatedOnly: $validateOnly,
                outputTruncated: $outputTruncated,
            );
        }
    }

    /**
     * Resolve and validate one server-owned profile.
     *
     * @return array<string, int|float>
     */
    public function profile(string $profile): array
    {
        $limits = config("code.profiles.{$profile}");
        if (! is_array($limits)) {
            throw new \InvalidArgumentException("Unknown Code Mode resource profile: {$profile}");
        }

        return $limits;
    }

    /**
     * Register the only host callbacks, capture them in trusted closures, then
     * remove the registration object before untrusted code can observe it.
     *
     * @param  list<array{level: string, text: string}>  $logs
     */
    private function installHostBootstrap(
        Sandbox $sandbox,
        ?CodeBridge $bridge,
        array &$logs,
        int &$outputBytes,
        bool &$outputTruncated,
        int $outputLimit,
        int $logLimit,
    ): void {
        $sandbox->register('__opencompany_host', [
            'capture' => function (string $level, array $values) use (
                &$logs,
                &$outputBytes,
                &$outputTruncated,
                $outputLimit,
                $logLimit,
            ): null {
                $level = in_array($level, ['log', 'info', 'warn', 'error'], true) ? $level : 'log';
                $text = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $values));
                $bytes = strlen($text) + 1;

                if (count($logs) >= $logLimit || $outputBytes + $bytes > $outputLimit) {
                    if (! $outputTruncated) {
                        $logs[] = [
                            'level' => 'warn',
                            'text' => '[console output truncated by the active resource profile]',
                        ];
                        $outputTruncated = true;
                    }

                    return null;
                }

                $logs[] = ['level' => $level, 'text' => $text];
                $outputBytes += $bytes;

                return null;
            },
            'call' => function (string $path, array $args) use ($bridge): array {
                if ($bridge === null) {
                    return [
                        'ok' => false,
                        'error' => [
                            'type' => 'capability_unavailable',
                            'message' => 'app.* capabilities are not available in this execution context.',
                            'details' => [],
                            'retryable' => false,
                        ],
                    ];
                }

                try {
                    return ['ok' => true, 'value' => $bridge->call($path, ...$args)];
                } catch (ScriptBridgeException $exception) {
                    return [
                        'ok' => false,
                        'error' => [
                            'type' => $exception->errorType,
                            'message' => $exception->getMessage(),
                            'details' => $exception->details,
                            'retryable' => $exception->retryable,
                        ],
                    ];
                } catch (\Throwable) {
                    // The app bridge is expected to sanitize provider failures.
                    // Keep this final boundary generic in case its own glue fails.
                    return [
                        'ok' => false,
                        'error' => [
                            'type' => 'callback_error',
                            'message' => "app.{$path} failed inside the host bridge.",
                            'details' => ['path' => $path],
                            'retryable' => false,
                        ],
                    ];
                }
            },
        ]);

        $bootstrap = $sandbox->load($this->bootstrapSource($bridge !== null), 'opencompany-bootstrap.js');
        $bootstrap();
    }

    /**
     * Trusted JavaScript that provides standard inspection and an infinitely
     * nested synchronous app.* Proxy without leaving raw callbacks reachable.
     */
    private function bootstrapSource(bool $withCapabilities): string
    {
        $appBootstrap = $withCapabilities ? <<<'JS'
            const makeNamespace = (path) => new Proxy(function () {}, {
                get(_target, property) {
                    if (property === 'then') return undefined;
                    if (typeof property === 'symbol') return undefined;
                    const child = path === '' ? property : `${path}.${property}`;
                    return makeNamespace(child);
                },
                apply(_target, _this, args) {
                    const envelope = hostCall(path, args);
                    if (envelope.ok) return envelope.value;

                    const details = envelope.error || {};
                    const error = new Error(`[${details.type || 'tool_error'}] ${details.message || 'Capability call failed.'}`);
                    error.name = 'OpenCompanyError';
                    error.type = details.type || 'tool_error';
                    error.details = details.details || {};
                    error.retryable = details.retryable === true;
                    throw error;
                },
            });

            Object.defineProperty(globalThis, 'app', {
                value: makeNamespace(''),
                writable: false,
                configurable: false,
                enumerable: true,
            });
            JS : '';

        return <<<'JS'
            const hostCapture = __opencompany_host.capture;
            const hostCall = __opencompany_host.call;
            delete globalThis.__opencompany_host;

            const inspect = (value) => {
                if (typeof value === 'string') return value;
                if (typeof value === 'undefined') return 'undefined';
                if (typeof value === 'bigint') return `${value}n`;
                if (typeof value === 'symbol') return String(value);
                if (typeof value === 'function') return `[Function${value.name ? `: ${value.name}` : ''}]`;
                if (value instanceof Error) return `${value.name}: ${value.message}`;

                const seen = [];
                try {
                    const encoded = JSON.stringify(value, (_key, current) => {
                        if (typeof current === 'bigint') return `${current}n`;
                        if (typeof current === 'function') return `[Function${current.name ? `: ${current.name}` : ''}]`;
                        if (typeof current === 'symbol') return String(current);
                        if (current && typeof current === 'object') {
                            if (seen.includes(current)) return '[Circular]';
                            seen.push(current);
                        }
                        return current;
                    }, 2);
                    return typeof encoded === 'undefined' ? String(value) : encoded;
                } catch (_error) {
                    return String(value);
                }
            };

            const emit = (level, values) => hostCapture(level, values.map(inspect));
            const consoleValue = Object.freeze({
                log: (...values) => emit('log', values),
                info: (...values) => emit('info', values),
                warn: (...values) => emit('warn', values),
                error: (...values) => emit('error', values),
            });

            // QuickJS exposes Promise primitives, but the sandbox deliberately
            // has no host job loop. Remove these misleading entry points so
            // agents fail synchronously instead of creating work that can never
            // be drained or observed.
            Object.defineProperty(globalThis, 'Promise', {
                value: undefined,
                writable: false,
                configurable: false,
            });
            Object.defineProperty(globalThis, 'queueMicrotask', {
                value: undefined,
                writable: false,
                configurable: false,
            });

            Object.defineProperty(globalThis, 'console', {
                value: consoleValue,
                writable: false,
                configurable: false,
                enumerable: true,
            });
            Object.defineProperty(globalThis, 'print', {
                value: consoleValue.log,
                writable: false,
                configurable: false,
                enumerable: true,
            });
            Object.defineProperty(globalThis, 'dump', {
                value: (value) => { consoleValue.log(value); return value; },
                writable: false,
                configurable: false,
                enumerable: true,
            });
            JS.$appBootstrap;
    }

    /**
     * Convert native/runtime failures into a stable repair contract.
     *
     * @param  array<string, mixed>  $effects
     * @return array{type: string, message: string, line: ?int, column: ?int, suggestion: string, retryable: bool, effectStatus: string}
     */
    private function describeError(\Throwable $exception, string $sourceName, array $effects): array
    {
        $type = match (true) {
            $exception instanceof CodeRuntimeException => $exception->errorType,
            $exception instanceof SyntaxException => 'syntax_error',
            $exception instanceof TimeoutException => 'timeout',
            $exception instanceof MemoryException => 'memory_limit',
            $exception instanceof StackException => 'stack_limit',
            $exception instanceof ConversionException => 'conversion_error',
            $exception instanceof CallbackException => 'callback_error',
            $exception instanceof QuickJsRuntimeException => $this->runtimeErrorType($exception->getMessage()),
            $exception instanceof QuickJsException => 'quickjs_error',
            default => 'host_error',
        };

        [$line, $column] = $this->extractLocation($exception->getMessage(), $sourceName);
        $retryable = (bool) ($effects['retryable'] ?? true)
            && ! in_array($type, ['timeout', 'memory_limit', 'stack_limit', 'host_error'], true);
        $effectStatus = ((int) ($effects['writesUnknown'] ?? 0)) > 0
            ? 'unknown'
            : (((int) ($effects['writesSucceeded'] ?? 0)) > 0 ? 'succeeded' : 'none');

        return [
            'type' => $type,
            'message' => $this->cleanQuickJsMessage($exception->getMessage(), $sourceName),
            'line' => $line,
            'column' => $column,
            'suggestion' => $this->suggestion($type, $line),
            'retryable' => $retryable,
            'effectStatus' => $effectStatus,
        ];
    }

    private function runtimeErrorType(string $message): string
    {
        return preg_match('/\[([a-z0-9_]+)\]/', $message, $matches) === 1
            ? $matches[1]
            : 'runtime_error';
    }

    /** @return array{?int, ?int} */
    private function extractLocation(string $message, string $sourceName): array
    {
        $pattern = '/'.preg_quote($sourceName, '/').':(\d+):(\d+)/';
        if (preg_match($pattern, $message, $matches) !== 1) {
            return [null, null];
        }

        // quickjs-sandbox wraps function bodies with two prefix lines.
        return [max(1, (int) $matches[1] - 2), (int) $matches[2]];
    }

    private function cleanQuickJsMessage(string $message, string $sourceName): string
    {
        $firstLine = trim(strtok($message, "\n") ?: $message);
        $firstLine = preg_replace('/^(?:OpenCompanyError|Error):\s*/', '', $firstLine) ?? $firstLine;
        $firstLine = preg_replace('/^\[[a-z0-9_]+\]\s*/', '', $firstLine) ?? $firstLine;

        return strlen($firstLine) > 1200 ? substr($firstLine, 0, 1197).'...' : $firstLine;
    }

    private function suggestion(string $type, ?int $line): string
    {
        $where = $line !== null ? " near line {$line}" : '';

        return match ($type) {
            'syntax_error' => "Fix the JavaScript syntax{$where}, then run code_exec in validate mode before executing it.",
            'unknown_function' => 'Use code_read_doc for the namespace and copy one of the suggested app.* paths.',
            'invalid_arguments' => 'Correct the named object using the documented parameter names, types, and enum values.',
            'timeout' => 'Bound loops and reduce in-memory transformations. Inspect prior effects before retrying.',
            'memory_limit' => 'Process fewer records at once and return a compact summary.',
            'stack_limit' => 'Replace deep recursion with an iterative loop.',
            'conversion_error' => 'Return only JSON-compatible values: null, booleans, numbers, strings, arrays, and plain objects.',
            'callback_budget_exceeded' => 'Split the workflow into fewer capability calls per execution.',
            'callback_result_too_large', 'result_too_large' => 'Narrow or page the query and return only the fields needed for the next decision.',
            default => "Inspect the error{$where} and the effect ledger before deciding whether to retry.",
        };
    }

    /** @param  list<array{level: string, text: string}>  $logs */
    private function renderOutput(array $logs): string
    {
        return implode("\n", array_map(
            static fn (array $entry): string => $entry['level'] === 'log'
                ? $entry['text']
                : '['.$entry['level'].'] '.$entry['text'],
            $logs,
        ));
    }

    /** @return array{callbacks: int, callbackWallTime: float, reads: int, writesSucceeded: int, writesUnknown: int, retryable: bool} */
    private function emptyEffects(): array
    {
        return [
            'callbacks' => 0,
            'callbackWallTime' => 0.0,
            'reads' => 0,
            'writesSucceeded' => 0,
            'writesUnknown' => 0,
            'retryable' => true,
        ];
    }

    private function cpuUsage(?Sandbox $sandbox): float
    {
        try {
            return $sandbox !== null ? round($sandbox->cpuUsage() * 1000, 1) : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function memoryUsage(?Sandbox $sandbox): ?int
    {
        try {
            return $sandbox?->memoryUsage();
        } catch (\Throwable) {
            return null;
        }
    }

    private function peakMemoryUsage(?Sandbox $sandbox): ?int
    {
        try {
            return $sandbox?->peakMemoryUsage();
        } catch (\Throwable) {
            return null;
        }
    }
}

/**
 * Internal preflight/resource failure that should use the normal result shape.
 */
final class CodeRuntimeException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorType,
        string $message,
    ) {
        parent::__construct($message);
    }
}
