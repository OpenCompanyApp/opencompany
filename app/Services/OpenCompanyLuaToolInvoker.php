<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Integrations\IntegrationRuntime;
use OpenCompany\IntegrationCore\Contracts\LuaToolInvoker;

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

        return $runtime->call($this->agent, $toolSlug, $args, $account);
    }

    public function getToolMeta(string $toolSlug): array
    {
        return $this->registry->getToolMetaBySlug($toolSlug);
    }
}
