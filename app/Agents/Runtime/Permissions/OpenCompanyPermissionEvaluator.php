<?php

namespace App\Agents\Runtime\Permissions;

use App\Agents\Runtime\Permissions\Checks\ApprovalRequirementCheck;
use App\Agents\Runtime\Permissions\Checks\IntegrationPermissionCheck;
use App\Agents\Runtime\Permissions\Checks\SessionGrantCheck;
use App\Agents\Runtime\Permissions\Checks\ToolPermissionCheck;
use App\Agents\Runtime\Permissions\Checks\WorkspaceBoundaryCheck;
use App\Models\User;

class OpenCompanyPermissionEvaluator
{
    public function __construct(
        private WorkspaceBoundaryCheck $workspaceBoundary,
        private ToolPermissionCheck $toolPermission,
        private IntegrationPermissionCheck $integrationPermission,
        private SessionGrantCheck $sessionGrant,
        private ApprovalRequirementCheck $approvalRequirement,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $toolMeta
     * @param  list<string>  $sessionGrants
     */
    public function evaluate(User $agent, string $toolSlug, array $context = [], array $toolMeta = [], array $sessionGrants = []): PermissionDecision
    {
        $boundary = $this->workspaceBoundary->evaluate($agent, $context);
        if ($boundary !== null) {
            return $boundary;
        }

        $integration = isset($toolMeta['integration'])
            ? $this->integrationPermission->evaluate($agent, (string) $toolMeta['integration'], (string) ($toolMeta['type'] ?? 'read'))
            : null;
        if ($integration !== null) {
            return $integration;
        }

        $grant = $this->sessionGrant->evaluate($toolSlug, $sessionGrants);
        if ($grant?->allowed()) {
            return $grant;
        }

        foreach ([
            $this->toolPermission->evaluate($agent, $toolSlug, (string) ($toolMeta['type'] ?? 'write')),
            $this->approvalRequirement->evaluate($toolMeta),
        ] as $decision) {
            if ($decision !== null) {
                return $decision;
            }
        }

        return PermissionDecision::allow();
    }
}
