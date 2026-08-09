<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Integrations\IntegrationRuntime;
use OpenCompany\IntegrationCore\Contracts\ScriptToolInvoker;

/**
 * Routes Code Mode bridge calls into OpenCompany's integration runtime.
 *
 * JavaScript programs must not instantiate tools directly. This invoker keeps
 * calls on the same permission, account-alias, and result-normalization path as
 * agent SDK tool calls.
 */
class OpenCompanyScriptToolInvoker implements ScriptToolInvoker
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
        // dispatch. Do not bypass it here just because Code Mode resolved a
        // function name to a tool slug.
        return $runtime->call($this->agent, $toolSlug, $args, $account);
    }

    public function getToolMeta(string $toolSlug): array
    {
        return $this->registry->getToolMetaBySlug($toolSlug);
    }
}
