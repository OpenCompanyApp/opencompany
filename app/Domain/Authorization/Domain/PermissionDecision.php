<?php

namespace App\Domain\Authorization\Domain;

/**
 * Effective authorization decision for one agent action.
 *
 * Existing controllers/tools still consume arrays through AgentPermissionService,
 * but the authorization context should reason in named decisions instead of
 * loosely shaped booleans. `canRequest` is used for user-visible access-request
 * flows when an action is denied but requestable.
 */
readonly class PermissionDecision
{
    public function __construct(
        public bool $allowed,
        public bool $requiresApproval = false,
        public bool $canRequest = false,
    ) {}

    public static function allow(bool $requiresApproval = false): self
    {
        return new self(true, $requiresApproval, false);
    }

    public static function deny(bool $canRequest = false): self
    {
        return new self(false, false, $canRequest);
    }

    /**
     * @return array{allowed: bool, requires_approval: bool}
     */
    public function toToolArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'requires_approval' => $this->requiresApproval,
        ];
    }

    /**
     * @return array{allowed: bool, can_request: bool}
     */
    public function toAccessArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'can_request' => $this->canRequest,
        ];
    }

    /**
     * @return array{allowed: bool, requires_approval: bool, can_request: bool}
     */
    public function toContactArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'requires_approval' => $this->requiresApproval,
            'can_request' => $this->canRequest,
        ];
    }
}
