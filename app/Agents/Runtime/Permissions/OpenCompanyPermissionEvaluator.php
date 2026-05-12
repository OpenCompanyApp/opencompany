<?php

namespace App\Agents\Runtime\Permissions;

use App\Agents\Runtime\Permissions\Checks\ApprovalRequirementCheck;
use App\Agents\Runtime\Permissions\Checks\IntegrationPermissionCheck;
use App\Agents\Runtime\Permissions\Checks\SessionGrantCheck;
use App\Agents\Runtime\Permissions\Checks\ToolPermissionCheck;
use App\Agents\Runtime\Permissions\Checks\WorkspaceBoundaryCheck;
use App\Models\User;

/**
 * Central permission decision point for agent tool execution.
 *
 * The evaluator composes small checks in a security-sensitive order. Keep this
 * class as the place where that order is visible; individual checks should own
 * their rule details, but they should not independently decide the global order.
 */
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
        // Workspace boundaries are absolute. A session grant can approve a tool
        // inside the current workspace, but it must never widen access to data,
        // agents, models, integrations, or MCP servers from another workspace.
        $boundary = $this->workspaceBoundary->evaluate($agent, $context);
        if ($boundary !== null) {
            return $boundary;
        }

        // Integration enablement is checked before session grants for the same
        // reason: a one-off grant may authorize an action, but it cannot enable
        // an integration that the workspace/agent configuration has disabled.
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

        // Tool-level permissions and approval requirements are evaluated after
        // workspace/integration gates. The first non-null decision wins, so keep
        // more fundamental boundary checks above this list.
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
