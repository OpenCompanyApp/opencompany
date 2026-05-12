<?php

namespace App\Agents\Runtime\Permissions\Checks;

use App\Agents\Runtime\Permissions\PermissionDecision;
use App\Models\User;
use App\Services\AgentPermissionService;

/**
 * Ensures an agent may use an integration before a tool call reaches runtime.
 *
 * This check accepts both legacy string IDs and newer descriptor arrays because
 * AgentPermissionService has returned both shapes over time. Do not narrow that
 * parsing unless all persisted permission config has been migrated.
 */
class IntegrationPermissionCheck
{
    public function __construct(private AgentPermissionService $permissions) {}

    public function evaluate(User $agent, string $integrationId, string $mode = 'read'): ?PermissionDecision
    {
        if (! method_exists($this->permissions, 'getEnabledIntegrations')) {
            return null;
        }

        $enabled = collect($this->permissions->getEnabledIntegrations($agent))
            ->contains(function (mixed $integration) use ($integrationId): bool {
                if (is_string($integration)) {
                    return $integration === $integrationId;
                }

                return is_array($integration) && ($integration['id'] ?? null) === $integrationId;
            });

        if (! $enabled) {
            return PermissionDecision::deny("Integration {$integrationId} is not enabled for this agent", 'integration_permission');
        }

        return null;
    }
}
