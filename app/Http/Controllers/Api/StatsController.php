<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceStatusService;

class StatsController extends Controller
{
    public function index(WorkspaceStatusService $statusService): \Illuminate\Http\JsonResponse
    {
        $status = $statusService->gather(workspace()->id);

        // Return all stats in camelCase format as expected by the frontend
        return response()->json([
            'agentsOnline' => $status['agents_online'],
            'totalAgents' => $status['agents_total'],
            'tasksCompleted' => $status['tasks_completed'],
            'tasksToday' => $status['tasks_today'],
            'messagesTotal' => $status['messages_total'],
            'messagesToday' => $status['messages_today'],
        ]);
    }

    /**
     * Detailed workspace status (agents with tasks, full stats).
     */
    public function status(WorkspaceStatusService $statusService): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            $statusService->gather(workspace()->id)
        );
    }
}
