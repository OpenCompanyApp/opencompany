<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Integrations\IntegrationRuntime;
use OpenCompany\IntegrationCore\Contracts\LuaToolInvoker;

/**
 * Routes Lua bridge calls into OpenCompany's integration runtime.
 *
 * Lua scripts should not instantiate tools directly. This invoker keeps Lua
 * calls on the same permission, account-alias, and result-normalization path as
 * agent SDK tool calls.
 */
class OpenCompanyLuaToolInvoker implements LuaToolInvoker
{
    public function __construct(
        private User $agent,
        private ToolRegistry $registry,
        private ?IntegrationRuntime $runtime = null,
    ) {}

    public function invoke(string $toolSlug, array $args, ?string $account = null): mixed
    {
        $runtime = $this->runtime ?? new IntegrationRuntime($this->registry);

        // IntegrationRuntime handles permission evaluation and package/MCP
        // dispatch. Do not bypass it here just because Lua already resolved a
        // function name to a tool slug.
        return $runtime->call($this->agent, $toolSlug, $args, $account);
    }

    public function getToolMeta(string $toolSlug): array
    {
        return $this->registry->getToolMetaBySlug($toolSlug);
    }
}
