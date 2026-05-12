<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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

            if ($value->workspace_id !== $agent->workspace_id) {
                return PermissionDecision::deny("{$key} is outside the agent workspace", 'workspace_boundary');
            }
        }

        return null;
    }
}
