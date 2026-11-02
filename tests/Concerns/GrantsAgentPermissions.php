<?php

namespace Tests\Concerns;

use App\Models\AgentPermission;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Test helpers that give an agent explicit admin permission rules.
 *
 * Under the policy model (Product Direction pillar 5) only declared reads run
 * without approval and peers outside the manager chain need approval to be
 * contacted. Tests that exercise *mechanics* (VFS writes, delegation,
 * receipts, ...) rather than policy use these helpers to write the same
 * per-agent AgentPermission rows an admin would create in the capabilities
 * UI, so the scenario runs without an approval gate. Policy tests should not
 * use this trait; they should assert the defaults directly.
 */
trait GrantsAgentPermissions
{
    /**
     * Allow the given tool slugs for the agent without approval
     * (scope `tool`, permission `allow`, requires_approval=false).
     */
    protected function allowTools(User $agent, string ...$slugs): void
    {
        foreach ($slugs as $slug) {
            $this->writeAllowRule($agent, 'tool', $slug);
        }
    }

    /**
     * Allow the agent to contact any agent without approval
     * (scope `agent`, key `*`, permission `allow`, requires_approval=false).
     */
    protected function allowAgentContact(User $agent): void
    {
        $this->writeAllowRule($agent, 'agent', '*');
    }

    /** Upsert one allow-without-approval rule, keeping an existing row's id. */
    private function writeAllowRule(User $agent, string $scopeType, string $scopeKey): void
    {
        $rule = AgentPermission::firstOrNew([
            'agent_id' => $agent->id,
            'scope_type' => $scopeType,
            'scope_key' => $scopeKey,
        ]);
        $rule->id ??= (string) Str::uuid();
        $rule->permission = 'allow';
        $rule->requires_approval = false;
        $rule->save();
    }
}
