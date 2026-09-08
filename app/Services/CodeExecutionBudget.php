<?php

namespace App\Services;

use Closure;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Applies a short-lived script execution budget to Code Mode transports.
 *
 * This app-owned boundary does not decide whether a tool is allowed, create
 * provider requests, or interrupt the PHP worker. CodeBridge establishes the
 * aggregate execution scope and each approved tool callback opens a child
 * scope. The registered Guzzle middleware and the explicit Symfony Process
 * runner see the remaining child deadline immediately before dispatch.
 *
 * The stack is deliberately process-local and restored in finally blocks. It
 * must therefore be entered around every script execution rather than treated
 * as a request-global timeout for unrelated host HTTP clients.
 */
final class CodeExecutionBudget
{
    /**
     * @var list<array{aggregate_deadline_ns: int, callback_deadline_ns: int|null, per_callback_ns: int, cancelled: Closure(): bool}>
     */
    private array $scopes = [];

    /**
     * Establish the aggregate lifetime for one script execution.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @param  Closure(): bool  $cancelled
     * @return T
     */
    public function within(int $aggregateMilliseconds, int $perCallbackMilliseconds, Closure $cancelled, Closure $callback): mixed
    {
        if ($aggregateMilliseconds <= 0 || $perCallbackMilliseconds <= 0) {
            throw new \InvalidArgumentException('Code execution deadlines must be positive milliseconds.');
        }

        $now = hrtime(true);
        $this->scopes[] = [
            'aggregate_deadline_ns' => $now + ($aggregateMilliseconds * 1_000_000),
            'callback_deadline_ns' => null,
            'per_callback_ns' => $perCallbackMilliseconds * 1_000_000,
            'cancelled' => $cancelled,
        ];

        try {
            $this->checkpoint();

            return $callback();
        } finally {
            array_pop($this->scopes);
        }
    }

    /**
     * Establish one fixed deadline for a script tool callback.
     *
     * Multiple HTTP requests made by this callback share this deadline; a
     * request cannot reset its allowance merely by dispatching after another
     * request returns.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function callback(Closure $callback): mixed
    {
        $scope = $this->activeScope();
        if ($scope === null) {
            throw new \LogicException('A code execution callback requires an active execution budget.');
        }

        $this->checkpoint();
        if ($scope['callback_deadline_ns'] !== null) {
            // Nested bridge helpers remain part of the parent tool callback;
            // opening one must not extend its already fixed allowance.
            return $callback();
        }

        $scope['callback_deadline_ns'] = min(
            $scope['aggregate_deadline_ns'],
            hrtime(true) + $scope['per_callback_ns'],
        );
        $this->scopes[] = $scope;

        try {
            $this->checkpoint();

            return $callback();
        } finally {
            try {
                $this->checkpoint();
            } finally {
                array_pop($this->scopes);
            }
        }
    }

    /**
     * Reject a cancelled or expired execution before user-controlled work.
     */
    public function checkpoint(): void
    {
        $scope = $this->activeScope();
        if ($scope === null) {
            return;
        }

        if (($scope['cancelled'])()) {
            throw new CodeExecutionCancelled;
        }

        if (hrtime(true) >= $this->deadline($scope)) {
            throw new CodeExecutionDeadlineExceeded;
        }
    }

    /**
     * Apply the active callback deadline immediately before Guzzle dispatch.
     *
     * @param  callable(RequestInterface, array<string, mixed>): mixed  $handler
     * @param  array<string, mixed>  $options
     */
    public function dispatch(callable $handler, RequestInterface $request, array $options): mixed
    {
        $scope = $this->activeScope();
        if ($scope === null || $scope['callback_deadline_ns'] === null) {
            return $handler($request, $options);
        }

        $this->checkpoint();
        $remainingSeconds = ($this->deadline($scope) - hrtime(true)) / 1_000_000_000;
        if ($remainingSeconds <= 0) {
            throw new CodeExecutionDeadlineExceeded;
        }

        // These transport settings are seconds. Preserve a caller's shorter
        // timeout, but replace absent or unbounded values with the callback's
        // remaining monotonic allowance before the handler performs I/O.
        foreach (['timeout', 'connect_timeout', 'read_timeout'] as $name) {
            $configured = $options[$name] ?? null;
            $options[$name] = is_int($configured) || is_float($configured)
                ? ($configured > 0 ? min((float) $configured, $remainingSeconds) : $remainingSeconds)
                : $remainingSeconds;
        }

        return $handler($request, $options);
    }

    /**
     * Run a local child process under the active callback deadline.
     *
     * Symfony Process is not a Guzzle transport, so the HTTP middleware cannot
     * constrain it. Under Code Mode we poll the child process and stop it on a
     * cancellation/deadline checkpoint. Outside an active callback this retains
     * the caller's ordinary Symfony timeout and does not affect host work.
     *
     * @throws CodeExecutionCancelled|CodeExecutionDeadlineExceeded
     */
    public function runProcess(Process $process, ?float $configuredTimeout = null): void
    {
        $scope = $this->activeScope();
        if ($scope === null || $scope['callback_deadline_ns'] === null) {
            if ($configuredTimeout !== null) {
                $process->setTimeout($configuredTimeout);
            }
            $process->run();

            return;
        }

        $timeout = $this->processTimeout($configuredTimeout);
        $process->setTimeout($timeout);
        $process->start();

        try {
            // A process can be silent, so output callbacks are insufficient for
            // cancellation. Polling keeps the child bounded without signals to
            // the PHP worker or a global host timeout mutation.
            while ($process->isRunning()) {
                // Symfony's configured timeout can be shorter than the Code
                // Mode allowance. Check it explicitly while polling; merely
                // asking isRunning() does not promise that enforcement.
                $process->checkTimeout();
                usleep(5_000);
                $this->checkpoint();
                $process->checkTimeout();
            }
            $process->wait();
            $this->checkpoint();
        } catch (CodeExecutionCancelled|CodeExecutionDeadlineExceeded $exception) {
            $process->stop(0);

            throw $exception;
        } catch (ProcessTimedOutException) {
            $process->stop(0);

            throw new CodeExecutionDeadlineExceeded;
        }
    }

    /**
     * Return a process timeout that cannot outlive the fixed callback deadline.
     *
     * @throws CodeExecutionCancelled|CodeExecutionDeadlineExceeded
     */
    public function processTimeout(?float $configuredTimeout): ?float
    {
        $scope = $this->activeScope();
        if ($scope === null || $scope['callback_deadline_ns'] === null) {
            return $configuredTimeout;
        }

        $this->checkpoint();
        $remainingSeconds = ($this->deadline($scope) - hrtime(true)) / 1_000_000_000;
        if ($remainingSeconds <= 0) {
            throw new CodeExecutionDeadlineExceeded;
        }

        return $configuredTimeout !== null && $configuredTimeout > 0
            ? min($configuredTimeout, $remainingSeconds)
            : $remainingSeconds;
    }

    /**
     * @return array{aggregate_deadline_ns: int, callback_deadline_ns: int|null, per_callback_ns: int, cancelled: Closure(): bool}|null
     */
    private function activeScope(): ?array
    {
        return $this->scopes === [] ? null : $this->scopes[array_key_last($this->scopes)];
    }

    /**
     * @param  array{aggregate_deadline_ns: int, callback_deadline_ns: int|null, per_callback_ns: int, cancelled: Closure(): bool}  $scope
     */
    private function deadline(array $scope): int
    {
        return $scope['callback_deadline_ns'] ?? $scope['aggregate_deadline_ns'];
    }
}
