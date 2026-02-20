<?php

namespace App\Http\Controllers\Api;

use App\Agents\Tools\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ToolCatalogController extends Controller
{
    public function index(ToolRegistry $toolRegistry): JsonResponse
    {
        // Use any workspace agent for schema extraction (schemas are static, agent doesn't matter)
        $agent = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->first();

        if (!$agent) {
            // No agents — return metadata without schemas
            return response()->json($toolRegistry->getAppGroupsMeta());
        }

        return response()->json($toolRegistry->getToolCatalog($agent));
    }
}
