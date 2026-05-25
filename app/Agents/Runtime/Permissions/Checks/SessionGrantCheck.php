<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;

/**
 * Checks temporary per-session grants created by approval or resume flows.
 *
 * Session grants are intentionally narrow string keys. They may allow a tool
 * that already passed workspace/integration boundaries, but the evaluator must
 * run those broader boundary checks before this class is consulted.
 */
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
