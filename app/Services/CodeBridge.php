<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use OpenCompany\IntegrationCore\Script\ScriptBridge;
use OpenCompany\IntegrationCore\Script\ScriptBridgeException;

/**
 * OpenCompany's budgeted wrapper around the shared app.* script bridge.
 *
 * Integration core owns catalog mapping and pre-dispatch schema validation.
 * This wrapper owns per-execution callback budgets, result-size limits,
 * secret-safe errors, and effect summaries. Authorization, workspace scope,
 * approvals, credentials, and provider execution remain on IntegrationRuntime.
 */
final class CodeBridge
{
    private ScriptBridge $bridge;

    private int $callbackCount = 0;

    private float $callbackWallSeconds = 0.0;

    /**
     * @param  array<string, int|float>  $profile  Resolved host-owned runtime profile
     */
    public function __construct(
        User $agent,
        ToolRegistry $registry,
        CodeApiDocGenerator $docGenerator,
        private readonly array $profile,
    ) {
        $this->bridge = new ScriptBridge(
            $docGenerator->buildFunctionMap($agent),
            $docGenerator->buildParameterMap($agent),
            new OpenCompanyScriptToolInvoker($agent, $registry),
            $docGenerator->buildAccountMap($agent),
        );
    }

    /**
     * Dispatch one synchronous capability call from QuickJS.
     *
     * @throws ScriptBridgeException when a host budget or catalog contract is violated
     */
    public function call(string $path, mixed ...$args): mixed
    {
        $limit = (int) ($this->profile['callback_limit'] ?? 0);
        if ($limit < 1 || $this->callbackCount >= $limit) {
            throw new ScriptBridgeException(
                'callback_budget_exceeded',
                "Code execution exceeded its {$limit}-call capability budget. Split the work into smaller executions.",
                ['limit' => $limit],
            );
        }

        $this->callbackCount++;
        $start = microtime(true);

        try {
            $result = $this->bridge->call($path, ...$args);
        } catch (ScriptBridgeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ScriptBridgeException(
                'tool_error',
                $this->safeToolError($path, $exception),
                ['path' => $path],
                retryable: $this->isExecutionRetryable(),
            );
        } finally {
            $elapsed = microtime(true) - $start;
            $this->callbackWallSeconds += $elapsed;
        }

        $elapsed = microtime(true) - $start;
        $singleLimit = (float) ($this->profile['callback_wall_limit'] ?? 0.0);
        $totalLimit = (float) ($this->profile['callback_total_wall_limit'] ?? 0.0);

        if (($singleLimit > 0 && $elapsed > $singleLimit)
            || ($totalLimit > 0 && $this->callbackWallSeconds > $totalLimit)) {
            throw new ScriptBridgeException(
                'callback_time_exceeded',
                'A capability call exceeded the execution wall-time budget. Its external effect may already have happened; inspect state before retrying.',
                ['path' => $path],
                retryable: false,
            );
        }

        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $resultLimit = (int) ($this->profile['callback_result_limit'] ?? 0);
        if ($encoded !== false && $resultLimit > 0 && strlen($encoded) > $resultLimit) {
            throw new ScriptBridgeException(
                'callback_result_too_large',
                'A capability returned too much data for one code execution. Narrow the query, page the request, or select fewer fields.',
                ['path' => $path, 'limit' => $resultLimit],
                retryable: $this->isExecutionRetryable(),
            );
        }

        return $result;
    }

    /**
     * Return the effect-aware ledger used by traces and retry decisions.
     *
     * @return list<array<string, mixed>>
     */
    public function getCallLog(): array
    {
        return array_map(function (array $entry): array {
            if (isset($entry['error'])) {
                $entry['error'] = $this->redactSecrets($entry['error']);
            }

            return $entry;
        }, $this->bridge->getCallLog());
    }

    /**
     * Summarize the execution's read/write effects without retaining arguments.
     *
     * @return array{callbacks: int, callbackWallTime: float, reads: int, writesSucceeded: int, writesUnknown: int, retryable: bool}
     */
    public function effectSummary(): array
    {
        $reads = 0;
        $writesSucceeded = 0;
        $writesUnknown = 0;

        foreach ($this->getCallLog() as $entry) {
            $effect = $entry['effect'] ?? 'none';
            if ($effect === 'none') {
                continue;
            }

            if ($effect !== 'write') {
                $reads++;

                continue;
            }

            if (($entry['effectStatus'] ?? 'unknown') === 'succeeded') {
                $writesSucceeded++;
            } elseif (($entry['effectStatus'] ?? 'unknown') === 'unknown') {
                $writesUnknown++;
            }
        }

        return [
            'callbacks' => $this->callbackCount,
            'callbackWallTime' => round($this->callbackWallSeconds * 1000, 1),
            'reads' => $reads,
            'writesSucceeded' => $writesSucceeded,
            'writesUnknown' => $writesUnknown,
            'retryable' => $writesSucceeded === 0 && $writesUnknown === 0,
        ];
    }

    /**
     * Whether re-running the whole program cannot duplicate a known/ambiguous write.
     */
    public function isExecutionRetryable(): bool
    {
        return $this->effectSummary()['retryable'];
    }

    /**
     * Remove common secret-bearing fragments while retaining enough provider
     * context for an agent to repair ordinary request errors.
     */
    private function safeToolError(string $path, \Throwable $exception): string
    {
        return "app.{$path} failed: ".$this->redactSecrets($exception->getMessage());
    }

    /**
     * Redact common credential forms before errors reach a model, trace, or UI.
     */
    private function redactSecrets(string $message): string
    {
        $message = preg_replace('/(?i)bearer\s+[A-Za-z0-9._~+\/-]+/', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/(?i)(authorization|api[_-]?key|access[_-]?token|secret|password)\s*[:=]\s*[^\s,;]+/', '$1=[redacted]', $message) ?? $message;
        $message = preg_replace('/([?&](?:key|token|secret|password)=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;

        if (strlen($message) > 1000) {
            $message = substr($message, 0, 997).'...';
        }

        return $message;
    }
}
