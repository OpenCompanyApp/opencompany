<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceStatusService;
use Illuminate\Http\Request;

/**
 * Workspace status API.
 *
 * Owns only the read-only `/api/stats/status` snapshot built by
 * WorkspaceStatusService for the current workspace. The former summary
 * `index` endpoint existed solely for the deleted Dashboard page and was
 * removed with it; do not re-add page-specific aggregates here.
 */
class StatsController extends Controller
{
    /**
     * Detailed workspace status (agents with tasks, full stats).
     */
    public function status(Request $request, WorkspaceStatusService $statusService): \Illuminate\Http\JsonResponse
    {
        $channelId = $request->query('channelId');
        $agentId = $request->query('agentId');

        return response()->json(
            $statusService->gather(
                workspace()->id,
                is_string($channelId) ? $channelId : null,
                is_string($agentId) ? $agentId : null,
            )
        );
    }
}
