<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;

class ApprovalRequirementCheck
{
    /**
     * @param  array<string, mixed>  $toolMeta
     */
    public function evaluate(array $toolMeta): ?PermissionDecision
    {
        if (($toolMeta['requires_approval'] ?? false) !== true) {
            return null;
        }

        return PermissionDecision::approvalRequired(
            'Tool requires approval',
            'approval_requirement',
            $toolMeta,
        );
    }
}
