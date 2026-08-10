<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuickJsSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Developer console endpoint for capability-empty QuickJS execution.
 *
 * This intentionally runs without an app bridge, so console snippets can test
 * JavaScript syntax/helpers without gaining agent tools or workspace data.
 */
final class CodeConsoleController extends Controller
{
    public function __construct(
        private QuickJsSandboxService $sandbox,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:524288',
            'mode' => 'nullable|string|in:validate,execute',
        ]);

        // No CodeBridge is passed here by design. Tool-capable execution only
        // happens on agent/automation paths with an explicit permission owner.
        $result = $this->sandbox->execute(
            code: $request->string('code')->toString(),
            profile: 'console',
            validateOnly: $request->input('mode', 'execute') === 'validate',
            sourceName: 'console-code.js',
        );

        return response()->json($result->toArray());
    }
}
