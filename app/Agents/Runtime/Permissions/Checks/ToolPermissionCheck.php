<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;
use App\Models\User;
use App\Services\AgentPermissionService;

class ToolPermissionCheck
{
    public function __construct(private AgentPermissionService $permissions) {}

    public function evaluate(User $agent, string $toolSlug, string $toolType = 'write'): ?PermissionDecision
    {
        $decision = $this->permissions->resolveToolPermission($agent, $toolSlug, $toolType);

        if (! ($decision['allowed'] ?? true)) {
            return PermissionDecision::deny("Tool {$toolSlug} is not allowed for this agent", 'tool_permission');
        }

        if ($decision['requires_approval'] ?? false) {
            return PermissionDecision::approvalRequired(
                "Tool {$toolSlug} requires approval",
                'tool_permission',
                [
                    'tool' => $toolSlug,
                    'type' => $toolType,
                ],
            );
        }

        return null;
    }
}
