<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Rejects runtime context that points outside the agent workspace.
 *
 * This is a coarse guard for model/tool contexts that pass Eloquent records into
 * permission evaluation. It should stay cheap and early; detailed model-level
 * authorization belongs closer to the tool or controller performing the action.
 */
class WorkspaceBoundaryCheck
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(User $agent, array $context = []): ?PermissionDecision
    {
        foreach ($context as $key => $value) {
            if (! $value instanceof Model || ! isset($value->workspace_id)) {
                continue;
            }

            // Compare persisted workspace IDs directly instead of relying on a
            // global currentWorkspace binding, which may be absent in queued or
            // live-test execution paths.
            if ($value->workspace_id !== $agent->workspace_id) {
                return PermissionDecision::deny("{$key} is outside the agent workspace", 'workspace_boundary');
            }
        }

        return null;
    }
}
