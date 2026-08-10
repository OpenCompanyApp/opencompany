<?php

namespace App\Services;

/**
 * Immutable outcome of one isolated QuickJS compilation or execution.
 *
 * This value owns model-safe diagnostics and resource telemetry. It does not
 * persist traces or expose raw provider exceptions; callers decide how much of
 * the structured payload belongs in agent output, task history, or the console.
 */
final class CodeExecutionResult
{
    /**
     * @param  list<array{level: string, text: string}>  $logs
     * @param  array<string, mixed>|null  $error
     * @param  array<string, mixed>  $effects
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $profile,
        public readonly string $output,
        public readonly array $logs,
        public readonly ?array $error,
        public readonly mixed $result,
        public readonly float $executionTime,
        public readonly float $cpuTime,
        public readonly ?int $memoryUsage,
        public readonly ?int $peakMemoryUsage,
        public readonly array $effects,
        public readonly bool $validatedOnly = false,
        public readonly bool $outputTruncated = false,
    ) {}

    /**
     * Return whether compilation/execution completed without a runtime error.
     */
    public function succeeded(): bool
    {
        return $this->error === null;
    }

    /**
     * Flatten the outcome for HTTP responses and durable trace metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId,
            'profile' => $this->profile,
            'output' => $this->output,
            'logs' => $this->logs,
            'error' => $this->error,
            'result' => $this->result,
            'executionTime' => $this->executionTime,
            'cpuTime' => $this->cpuTime,
            'memoryUsage' => $this->memoryUsage,
            'peakMemoryUsage' => $this->peakMemoryUsage,
            'effects' => $this->effects,
            'validatedOnly' => $this->validatedOnly,
            'outputTruncated' => $this->outputTruncated,
        ];
    }
}
