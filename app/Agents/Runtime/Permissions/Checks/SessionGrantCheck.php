<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;

class SessionGrantCheck
{
    /**
     * @param  list<string>  $grants
     */
    public function evaluate(string $key, array $grants = []): ?PermissionDecision
    {
        return in_array($key, $grants, true)
            ? PermissionDecision::allow('Allowed by session grant', 'session_grant')
            : null;
    }
}
