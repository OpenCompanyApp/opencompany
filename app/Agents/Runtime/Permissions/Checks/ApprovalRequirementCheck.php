<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;

/**
 * Converts tool metadata into an approval-required runtime decision.
 *
 * This check is intentionally metadata-only. It does not decide whether the
 * agent may use the tool; it only preserves an explicit approval requirement
 * after broader workspace, integration, and tool permission checks have run.
 */
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
