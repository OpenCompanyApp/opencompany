<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use OpenCompany\IntegrationCore\Lua\LuaBridge as SharedLuaBridge;

/**
 * App-specific wrapper around the shared integration-core Lua bridge.
 *
 * The sibling integration package owns argument mapping and Lua call dispatch.
 * OpenCompany supplies the agent-specific function map, account map, and tool
 * invoker so Lua scripts execute under the same permissions as normal tools.
 */
class LuaBridge
{
    private SharedLuaBridge $bridge;

    public function __construct(
        User $agent,
        ToolRegistry $registry,
        LuaApiDocGenerator $docGenerator,
    ) {
        $this->bridge = new SharedLuaBridge(
            $docGenerator->buildFunctionMap($agent),
            $docGenerator->buildParameterMap($agent),
            new OpenCompanyLuaToolInvoker($agent, $registry),
            $docGenerator->buildAccountMap($agent),
        );
    }

    public function call(string $path, mixed ...$args): mixed
    {
        // Delegate to the package bridge so OpenCompany does not duplicate the
        // Lua function binding semantics used by package integrations.
        return $this->bridge->call($path, ...$args);
    }

    /** @return list<array{path: string, durationMs: float, status: string, error?: string, icon?: string, name?: string, group?: string}> */
    public function getCallLog(): array
    {
        return $this->bridge->getCallLog();
    }
}
