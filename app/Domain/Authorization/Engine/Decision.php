<?php

namespace App\Domain\Authorization\Engine;

/**
 * One authorization answer: allow, deny or approval_required, with a stable
 * machine reason code, a human sentence and the decision trace.
 *
 * `reasonCode` is the contract: conformance vectors, the ledger, API error
 * bodies and a future Rust implementation all compare codes, never the
 * English `reason`. The trace lists the steps the engine passed, in order,
 * so "why can't X do Y?" is answerable from one decision.
 */
final readonly class Decision
{
    public const ALLOW = 'allow';
    public const DENY = 'deny';
    public const APPROVAL_REQUIRED = 'approval_required';

    /**
     * @param  list<string>  $trace
     */
    public function __construct(
        public string $effect,
        public string $reasonCode,
        public string $reason,
        public array $trace,
        public string $policyVersion,
    ) {}

    public function allowed(): bool
    {
        return $this->effect === self::ALLOW;
    }

    public function denied(): bool
    {
        return $this->effect === self::DENY;
    }

    public function requiresApproval(): bool
    {
        return $this->effect === self::APPROVAL_REQUIRED;
    }

    /**
     * @return array{effect: string, reason_code: string, reason: string, trace: list<string>, policy_version: string}
     */
    public function toArray(): array
    {
        return [
            'effect' => $this->effect,
            'reason_code' => $this->reasonCode,
            'reason' => $this->reason,
            'trace' => $this->trace,
            'policy_version' => $this->policyVersion,
        ];
    }
}
