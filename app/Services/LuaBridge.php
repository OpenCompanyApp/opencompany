<?php

namespace App\Services;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use OpenCompany\IntegrationCore\Lua\LuaBridge as SharedLuaBridge;

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
        );
    }

    public function call(string $path, mixed ...$args): mixed
    {
        return $this->bridge->call($path, ...$args);
    }

    /** @return list<array{path: string, durationMs: float, status: string, error?: string, icon?: string, name?: string, group?: string}> */
    public function getCallLog(): array
    {
        return $this->bridge->getCallLog();
    }
}
