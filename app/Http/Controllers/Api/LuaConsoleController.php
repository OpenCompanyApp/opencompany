<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LuaSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Developer console endpoint for raw Lua sandbox execution.
 *
 * This intentionally runs without an app bridge, so console snippets can test
 * Lua syntax/helpers without gaining access to agent tools or workspace data.
 */
class LuaConsoleController extends Controller
{
    public function __construct(
        private LuaSandboxService $lua,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50000',
        ]);

        // No LuaBridge is passed here by design. Tool-capable Lua execution
        // happens through agent/runtime paths where permissions are available.
        $result = $this->lua->execute($request->input('code'));

        return response()->json($result->toArray());
    }
}
