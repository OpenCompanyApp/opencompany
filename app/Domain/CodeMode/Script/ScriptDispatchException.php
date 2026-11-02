<?php

namespace App\Domain\CodeMode\Script;

/**
 * Host-authored disposition for a callback rejected before provider dispatch.
 *
 * This is intentionally separate from ScriptBridgeException: provider code may
 * use ordinary runtime exceptions, but only the host authorization boundary can
 * mark a call as denied or awaiting human approval in the shared effect ledger.
 */
final class ScriptDispatchException extends \RuntimeException
{
    public const AUTHORIZATION_DENIED = 'authorization_denied';

    public const APPROVAL_PENDING = 'approval_pending';

    /**
     * @param  array<string, mixed>  $details  Safe callback state without arguments or credentials
     */
    private function __construct(
        public readonly string $errorType,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /** Create a pre-dispatch denial that cannot be mistaken for provider ambiguity. */
    public static function denied(string $reason): self
    {
        return new self(self::AUTHORIZATION_DENIED, $reason);
    }

    /** Create a pending approval state while preserving the durable approval ID. */
    public static function approvalPending(string $approvalId): self
    {
        return new self(
            self::APPROVAL_PENDING,
            "This callback requires human approval (ID: {$approvalId}). The Ruby program stopped before the callback ran.",
            ['approval_id' => $approvalId],
        );
    }
}
