<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LuaSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $result = $this->lua->execute($request->input('code'));

        return response()->json($result->toArray());
    }
}
