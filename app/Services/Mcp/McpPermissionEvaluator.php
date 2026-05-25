<?php

namespace App\Services\Mcp;

use App\Agents\Runtime\Permissions\OpenCompanyPermissionEvaluator;
use App\Agents\Runtime\Permissions\PermissionDecision;
use App\Models\McpServer;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Adapts MCP server/tool names to the OpenCompany permission evaluator.
 *
 * MCP tools are stored as integration-like scopes: mcp_{server}__{tool}, which
 * lets regular tool permissions and session grants cover external MCP calls.
 */
class McpPermissionEvaluator
{
    public function __construct(private OpenCompanyPermissionEvaluator $permissions) {}

    public function evaluate(User $agent, McpServer $server, string $toolName): PermissionDecision
    {
        // Normalize tool names the same way McpToolProvider does so permission
        // rows remain stable across provider naming differences.
        return $this->permissions->evaluate($agent, 'mcp_'.$server->slug.'__'.Str::snake(str_replace('-', '_', $toolName)), [
            'server' => $server,
        ], [
            'integration' => 'mcp_'.$server->slug,
            'type' => 'write',
        ]);
    }
}
