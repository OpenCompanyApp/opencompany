<?php

namespace App\Domain\CodeMode\Script;

/**
 * Stable, agent-repairable failure raised before or during script bridge calls.
 *
 * The message is safe for an agent when the throwing bridge constructed it.
 * Host/provider exceptions remain host-owned and must be sanitized separately.
 */
final class ScriptBridgeException extends \RuntimeException
{
    /**
     * @param  array<string, mixed>  $details  Structured repair context without secrets or tool arguments
     */
    public function __construct(
        public readonly string $errorType,
        string $message,
        public readonly array $details = [],
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
