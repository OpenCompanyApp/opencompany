<?php

namespace App\Services\Mcp;

use App\Agents\Runtime\Permissions\OpenCompanyPermissionEvaluator;
use App\Agents\Runtime\Permissions\PermissionDecision;
use App\Models\McpServer;
use App\Models\User;
use Illuminate\Support\Str;

class McpPermissionEvaluator
{
    public function __construct(private OpenCompanyPermissionEvaluator $permissions) {}

    public function evaluate(User $agent, McpServer $server, string $toolName): PermissionDecision
    {
        return $this->permissions->evaluate($agent, 'mcp_'.$server->slug.'__'.Str::snake(str_replace('-', '_', $toolName)), [
            'server' => $server,
        ], [
            'integration' => 'mcp_'.$server->slug,
            'type' => 'write',
        ]);
    }
}
