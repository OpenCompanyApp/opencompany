<?php

namespace App\Http\Controllers\Api;

use App\Agents\Tools\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Services\LuaApiDocGenerator;
use Illuminate\Http\JsonResponse;

class ToolCatalogController extends Controller
{
    public function index(ToolRegistry $toolRegistry, LuaApiDocGenerator $docGenerator): JsonResponse
    {
        // Use any workspace agent for schema extraction (schemas are static, agent doesn't matter)
        $agent = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->first();

        if (!$agent) {
            // No agents — return metadata without schemas
            return response()->json([
                'groups' => $toolRegistry->getAppGroupsMeta(),
                'staticDocs' => [],
            ]);
        }

        $catalog = $toolRegistry->getToolCatalog($agent);

        // Convert parameter names to snake_case for Lua convention display
        foreach ($catalog as &$group) {
            foreach ($group['tools'] as &$tool) {
                foreach ($tool['parameters'] as &$param) {
                    $param['name'] = strtolower(preg_replace('/[A-Z]/', '_$0', $param['name']));
                }
            }
        }
        unset($group, $tool, $param);

        // Build slug → lua function path map
        $fnMap = $docGenerator->buildFunctionMap($agent);
        $slugToLua = array_flip($fnMap);

        // Workspace-enabled integration IDs for the enabled flag
        $enabledIntegrationIds = IntegrationSetting::forWorkspace()
            ->where('enabled', true)
            ->pluck('integration_id')
            ->toArray();

        // Enrich each group with Lua metadata
        foreach ($catalog as &$group) {
            $appName = $group['name'];
            $nsKey = ($group['isIntegration'] ? 'integrations.' : '') . $appName;

            // Lua namespace (e.g. "app.chat")
            $group['luaNamespace'] = 'app.' . $nsKey;

            // Supplementary Lua docs from ProvidesLuaDocs providers
            $group['luaDocs'] = $docGenerator->getSupplementaryDocs($nsKey);

            // Workspace-level enabled flag for integrations
            // Built-in groups are always enabled; MCP servers self-register when enabled
            if ($group['isIntegration']) {
                $group['enabled'] = str_starts_with($appName, 'mcp_')
                    || in_array($appName, $enabledIntegrationIds);
            }

            // Enrich each tool with its Lua function name
            foreach ($group['tools'] as &$tool) {
                $luaPath = $slugToLua[$tool['slug']] ?? null;
                if ($luaPath) {
                    $parts = explode('.', $luaPath);
                    $tool['luaFunction'] = end($parts);
                }
            }
        }

        return response()->json([
            'groups' => $catalog,
            'staticDocs' => $docGenerator->getStaticDocsForCatalog(),
        ]);
    }
}
