<?php

namespace App\Services;

use Bowerbird\RubyEngine\CapabilityFailure;
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
        ?\Closure $cancelled = null,
    ): CodeExecutionResult {
        $limits = $this->profile($profile);
        $started = hrtime(true);
        $cancelled ??= static fn (): bool => false;
        try {
            $client = new Client((string) config('code.engine_binary'), $this->engineDigest());
            $budget = app(CodeExecutionBudget::class);
            $hostFailure = null;
            $callbacks = [];
            foreach ($validateOnly ? [] : ($bridge?->capabilities() ?? []) as $path => $callback) {
                $callbacks[$path] = static function (array $args) use ($budget, $callback, &$hostFailure): mixed {
                    if ($hostFailure !== null) {
                        throw new CapabilityFailure($hostFailure['type'], $hostFailure['message']);
                    }
                    try {
                        return $budget->callback(static function () use ($budget, $callback, $args): mixed {
                            $value = $callback($args);
                            // Providers may catch transport exceptions and return
                            // an error payload. Expiry cannot become Ruby success.
                            $budget->checkpoint();

                            return $value;
                        });
                    } catch (CodeExecutionCancelled) {
                        $hostFailure = ['type' => 'cancelled', 'message' => 'Execution was cancelled. Inspect earlier effects before retrying.'];
                        throw new CapabilityFailure($hostFailure['type'], $hostFailure['message']);
                    } catch (CodeExecutionDeadlineExceeded) {
                        $hostFailure = ['type' => 'callback_time_exceeded', 'message' => 'The callback deadline expired. Inspect earlier effects before retrying.'];
                        throw new CapabilityFailure($hostFailure['type'], $hostFailure['message']);
                    }
                };
            }
            $checkpoint = static function () use ($budget, &$hostFailure): bool {
                try {
                    $budget->checkpoint();

                    return false;
                } catch (CodeExecutionCancelled) {
                    $hostFailure = ['type' => 'cancelled', 'message' => 'Execution was cancelled. Inspect earlier effects before retrying.'];
                } catch (CodeExecutionDeadlineExceeded) {
                    $hostFailure = ['type' => 'callback_time_exceeded', 'message' => 'The execution deadline expired. Inspect earlier effects before retrying.'];
                }

                return true;
            };
            $execution = $budget->within(
                aggregateMilliseconds: (int) min($limits['wall_limit_ms'], ($limits['callback_total_wall_limit'] > 0 ? $limits['callback_total_wall_limit'] * 1000 : $limits['wall_limit_ms'])),
                perCallbackMilliseconds: max(1, (int) ($limits['callback_wall_limit'] * 1000)),
                cancelled: $cancelled,
                callback: fn () => $client->execute(
                    source: $code,
                    capabilities: $callbacks,
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
                    cancelled: $checkpoint,
                ),
            );
            $logs = array_map(static fn (array $values): array => [
                'level' => 'log',
                'text' => implode(' ', array_map(static fn (mixed $value): string => is_string($value)
                    ? $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $values)),
            ], $execution->logs);
            $effects = $bridge?->effectSummary() ?? $this->emptyEffects();
            $error = $hostFailure ?? $execution->error;
            // A guest can rescue a Ruby exception but cannot turn a host-owned
            // pending approval or denial into a successfully completed program.
            if (($effects['callbacksPendingApproval'] ?? 0) > 0 || ($effects['callbacksDenied'] ?? 0) > 0) {
                foreach ($bridge?->getCallLog() ?? [] as $entry) {
                    if (in_array($entry['errorType'] ?? '', ['approval_pending', 'authorization_denied'], true)) {
                        $error = ['type' => $entry['errorType'], 'message' => $entry['error']];
                        break;
                    }
                }
            }
            if ($cancelled()) {
                $error = ['type' => 'cancelled', 'message' => 'Execution was cancelled. Inspect earlier effects before retrying.'];
            }

            return new CodeExecutionResult(
                executionId: $execution->executionId,
                profile: $profile,
                output: implode("\n", array_column($logs, 'text')),
                logs: $logs,
                error: $error === null ? null : $this->diagnostic($error, $effects),
                result: $error === null ? $execution->result : null,
                executionTime: $execution->wallMilliseconds,
                cpuTime: isset($execution->usage['cpu_ms']) ? (float) $execution->usage['cpu_ms'] : null,
                memoryUsage: null,
                peakMemoryUsage: isset($execution->usage['peak_memory_bytes']) ? (int) $execution->usage['peak_memory_bytes'] : null,
                effects: $effects,
                validatedOnly: $validateOnly,
            );
        } catch (\Throwable $exception) {
            // Startup errors may contain installation paths or service details.
            // Operators diagnose the configured artifact; agents get a safe message.
            $effects = $bridge?->effectSummary() ?? $this->emptyEffects();

            return new CodeExecutionResult(
                executionId: (string) Str::uuid(), profile: $profile, output: '', logs: [],
                error: $this->diagnostic(['type' => $exception instanceof CodeExecutionCancelled ? 'cancelled' : 'engine_unavailable',
                    'message' => $exception instanceof CodeExecutionCancelled
                        ? 'Execution was cancelled before startup.'
                        : 'The pinned Ruby engine is unavailable. Ask an operator to verify its installation.'], $effects),
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
            'writesSucceeded' => 0, 'writesUnknown' => 0,
            'writesPendingApproval' => 0, 'callbacksDenied' => 0, 'retryable' => true];
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
            : ($effects['writesSucceeded'] > 0 ? 'succeeded'
                : (($effects['writesPendingApproval'] ?? 0) > 0 ? 'pending'
                    : (($effects['callbacksDenied'] ?? 0) > 0 ? 'denied' : 'none')));

        $suggestion = match ($effectStatus) {
            'pending' => 'The callback was not executed. Wait for the recorded approval decision; do not automatically rerun this script.',
            'denied' => 'No callback ran. Update permissions or integration enablement before creating a new execution.',
            'unknown', 'succeeded' => 'Inspect confirmed and ambiguous effects before changing or rerunning this script.',
            default => 'Check the source location and code_read_doc contract; use mode: validate before execution.',
        };

        return [...$error,
            'line' => $error['line'] ?? null,
            'column' => $error['column'] ?? null,
            'suggestion' => $suggestion,
            'retryable' => (bool) $effects['retryable'] && ! in_array($error['type'] ?? '', ['engine_unavailable', 'host_transport_error', 'cancelled', 'approval_pending', 'authorization_denied', 'callback_time_exceeded'], true),
            'effectStatus' => $effectStatus,
        ];
    }
}
