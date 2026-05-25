<?php

namespace App\Agents\Runtime\Permissions;

class PermissionDecision
{
    /**
     * @param  array<string, mixed>  $approvalPayload
     */
    public function __construct(
        public readonly string $decision,
        public readonly string $reason,
        public readonly string $source,
        public readonly array $approvalPayload = [],
    ) {}

    public static function allow(string $reason = 'Allowed', string $source = 'runtime'): self
    {
        return new self('allow', $reason, $source);
    }

    public static function deny(string $reason, string $source): self
    {
        return new self('deny', $reason, $source);
    }

    /**
     * @param  array<string, mixed>  $approvalPayload
     */
    public static function approvalRequired(string $reason, string $source, array $approvalPayload = []): self
    {
        return new self('approval_required', $reason, $source, $approvalPayload);
    }

    public function allowed(): bool
    {
        return $this->decision === 'allow';
    }
}
