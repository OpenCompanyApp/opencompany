<?php

namespace App\Http\Controllers\Api;

use App\Agents\Tools\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\CodeApiDocGenerator;
use Illuminate\Http\JsonResponse;

/**
 * Read-only catalog endpoint for agent tools and Code Mode documentation.
 *
 * This endpoint is for developer UI/documentation. Tool execution still flows
 * through ToolRegistry/IntegrationRuntime so displaying a tool here does not
 * grant permission to call it.
 */
final class ToolCatalogController extends Controller
{
    public function index(ToolRegistry $toolRegistry, CodeApiDocGenerator $docGenerator): JsonResponse
    {
        // Use any workspace agent for schema extraction. Tool schemas are static
        // enough for docs, while permission-aware execution remains per-agent.
        $agent = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->first();

        if (! $agent) {
            // No agents means there is no agent-specific tool map to inspect,
            // but group metadata can still render the catalog shell.
            return response()->json([
                'groups' => $toolRegistry->getAppGroupsMeta(),
                'staticDocs' => [],
            ]);
        }

        $catalog = $toolRegistry->getToolCatalog($agent);

        // Code Mode uses snake_case names even when PHP/Laravel AI schemas use
        // camelCase. This is display-only; runtime mapping stays in CodeBridge.
        foreach ($catalog as &$group) {
            foreach ($group['tools'] as &$tool) {
                foreach ($tool['parameters'] as &$param) {
                    $param['name'] = strtolower(preg_replace('/[A-Z]/', '_$0', $param['name']));
                }
            }
        }
        unset($group, $tool, $param);

        // Build slug -> function path map so the UI shows the exact callable.
        $fnMap = $docGenerator->buildFunctionMap($agent);
        $slugToCode = array_flip($fnMap);

        // Workspace-level enabled flag is informational for integration groups.
        $enabledIntegrationIds = IntegrationSetting::forWorkspace()
            ->where('enabled', true)
            ->pluck('integration_id')
            ->toArray();

        // Enrich each group with Code Mode metadata.
        foreach ($catalog as &$group) {
            $appName = $group['name'];
            // MCP tools use app.mcp.{server} to avoid colliding with package
            // integration namespaces.
            if (str_starts_with($appName, 'mcp_')) {
                $serverName = substr($appName, strlen('mcp_'));
                $nsKey = 'mcp.'.$serverName;
            } else {
                $nsKey = ($group['isIntegration'] ? 'integrations.' : '').$appName;
            }
            $group['codeNamespace'] = 'app.'.$nsKey;

            // Supplementary script docs are package-owned and optional.
            $group['scriptDocs'] = $docGenerator->getSupplementaryDocs($nsKey);

            // Built-in groups are always enabled. MCP servers self-register only
            // while enabled, so their catalog presence is already sufficient.
            if ($group['isIntegration']) {
                $group['enabled'] = str_starts_with($appName, 'mcp_')
                    || in_array($appName, $enabledIntegrationIds);
            }

            // Enrich each tool with its Code Mode function name.
            foreach ($group['tools'] as &$tool) {
                $codePath = $slugToCode[$tool['slug']] ?? null;
                if ($codePath) {
                    $parts = explode('.', $codePath);
                    $tool['codeFunction'] = end($parts);
                }
            }
        }

        return response()->json([
            'groups' => $catalog,
            'staticDocs' => $docGenerator->getStaticDocsForCatalog(),
        ]);
    }
}
