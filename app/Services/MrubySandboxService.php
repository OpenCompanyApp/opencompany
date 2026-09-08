<?php

namespace App\Services;

use Bowerbird\RubyEngine\Client;
use Illuminate\Support\Str;

/**
 * Executes OpenCompany's sole Ruby profile using the shared isolated guest.
 *
 * This application adapter owns profile selection and model-safe presentation.
 * The shared engine owns process/VM isolation, while CodeBridge and the existing
 * integration runtime retain workspace, actor, permission and effect authority.
 */
final class MrubySandboxService
{
    /**
     * Compile without executing, or run one Ruby source with scoped capabilities.
     *
     * @param  array<string,mixed>  $globals  Data-only automation ctx
     */
    public function execute(
        string $code,
        string $profile = 'agent',
        ?CodeBridge $bridge = null,
        array $globals = [],
        bool $validateOnly = false,
        string $sourceName = 'opencompany-code.rb',
    ): CodeExecutionResult {
        $limits = $this->profile($profile);
        $started = hrtime(true);
        try {
            $client = new Client((string) config('code.engine_binary'), $this->engineDigest());
            $execution = $client->execute(
                source: $code,
                capabilities: $validateOnly ? [] : ($bridge?->capabilities() ?? []),
                globals: $globals,
                validateOnly: $validateOnly,
                limits: [
                    'memory_bytes' => (int) $limits['memory_limit'],
                    'instructions' => (int) $limits['instruction_limit'],
                    'wall_ms' => (int) $limits['wall_limit_ms'],
                    'cpu_ms' => (int) ($limits['cpu_limit_ms'] ?? 1000),
                    'source_bytes' => (int) $limits['source_limit'],
                    'result_bytes' => (int) $limits['result_limit'],
                    'calls' => (int) $limits['callback_limit'],
                    'log_bytes' => (int) $limits['output_limit'],
                ],
                filename: $sourceName,
            );
            $logs = array_map(static fn (array $values): array => [
                'level' => 'log',
                'text' => implode(' ', array_map(static fn (mixed $value): string => is_string($value)
                    ? $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $values)),
            ], $execution->logs);
            $effects = $bridge?->effectSummary() ?? $this->emptyEffects();

            return new CodeExecutionResult(
                executionId: $execution->executionId,
                profile: $profile,
                output: implode("\n", array_column($logs, 'text')),
                logs: $logs,
                error: $execution->error === null ? null : $this->diagnostic($execution->error, $effects),
                result: $execution->result,
                executionTime: $execution->wallMilliseconds,
                cpuTime: isset($execution->usage['cpu_ms']) ? (float) $execution->usage['cpu_ms'] : null,
                memoryUsage: null,
                peakMemoryUsage: isset($execution->usage['peak_memory_bytes']) ? (int) $execution->usage['peak_memory_bytes'] : null,
                effects: $effects,
                validatedOnly: $validateOnly,
            );
        } catch (\Throwable) {
            // Startup errors may contain installation paths or service details.
            // Operators diagnose the configured artifact; agents get a safe message.
            $effects = $bridge?->effectSummary() ?? $this->emptyEffects();

            return new CodeExecutionResult(
                executionId: (string) Str::uuid(), profile: $profile, output: '', logs: [],
                error: $this->diagnostic(['type' => 'engine_unavailable',
                    'message' => 'The pinned Ruby engine is unavailable. Ask an operator to verify its installation.'], $effects),
                result: null, executionTime: (hrtime(true) - $started) / 1_000_000,
                cpuTime: null, memoryUsage: null, peakMemoryUsage: null, effects: $effects,
                validatedOnly: $validateOnly,
            );
        }
    }

    /** @return array<string,int|float> Host-owned budget; agents cannot override limits. */
    public function profile(string $profile): array
    {
        if (! in_array($profile, ['agent', 'automation', 'console'], true)) {
            throw new \InvalidArgumentException('Unknown Code Mode resource profile.');
        }

        return config("code.profiles.{$profile}");
    }

    /** The installed artifact identity binds validation to the exact guest build. */
    public function engineDigest(): string
    {
        $binary = (string) config('code.engine_binary');
        if (! is_file($binary) || ! is_executable($binary)) {
            throw new \RuntimeException('The pinned Ruby engine is unavailable.');
        }
        $digest = hash_file('sha256', $binary);
        $expected = config('code.engine_sha256');
        if ($digest === false || ($expected !== null && ! hash_equals((string) $expected, $digest))) {
            throw new \RuntimeException('The installed Ruby engine does not match its pinned artifact.');
        }

        return $digest;
    }

    /** @return array<string,int|float|bool> */
    private function emptyEffects(): array
    {
        return ['callbacks' => 0, 'callbackWallTime' => 0.0, 'reads' => 0,
            'writesSucceeded' => 0, 'writesUnknown' => 0, 'retryable' => true];
    }

    /**
     * Combine guest diagnostics with host effect knowledge. A syntax repair is
     * not permission to repeat a completed or ambiguously delivered mutation.
     *
     * @param  array<string,mixed>  $error
     * @param  array<string,mixed>  $effects
     * @return array<string,mixed>
     */
    private function diagnostic(array $error, array $effects): array
    {
        $effectStatus = $effects['writesUnknown'] > 0 ? 'unknown'
            : ($effects['writesSucceeded'] > 0 ? 'succeeded' : 'none');

        return [...$error,
            'line' => $error['line'] ?? null,
            'column' => $error['column'] ?? null,
            'suggestion' => $effectStatus !== 'none'
                ? 'Inspect confirmed and ambiguous effects before changing or rerunning this script.'
                : 'Check the source location and code_read_doc contract; use mode: validate before execution.',
            'retryable' => (bool) $effects['retryable'] && ! in_array($error['type'] ?? '', ['engine_unavailable', 'host_transport_error'], true),
            'effectStatus' => $effectStatus,
        ];
    }
}
