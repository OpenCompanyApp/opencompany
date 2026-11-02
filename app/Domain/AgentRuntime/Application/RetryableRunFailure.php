<?php

namespace App\Domain\AgentRuntime\Application;

/**
 * Classifies transient infrastructure/provider failures for logical retries.
 *
 * Queue transport retries only failures this classifier considers repairable.
 * Permanent validation, authorization, and application errors become terminal
 * immediately so the UI and worker cannot disagree about task state.
 */
class RetryableRunFailure
{
    public function handle(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        foreach ([
            'connection refused',
            'connection timed out',
            'operation timed out',
            'timeout',
            'temporarily unavailable',
            'too many requests',
            'rate limit',
            'status code 429',
            'status code 500',
            'status code 502',
            'status code 503',
            'status code 504',
            'server error',
            'already executing another run',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
