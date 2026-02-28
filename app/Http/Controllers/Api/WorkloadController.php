<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class WorkloadController extends Controller
{
    public function index(): JsonResponse
    {
        $workspaceId = workspace()->id;
        $weekStart = Carbon::now()->startOfWeek();
        $todayStart = Carbon::today();

        $agents = User::where('type', 'agent')
            ->where('workspace_id', $workspaceId)
            ->orderByRaw("CASE status WHEN 'working' THEN 1 WHEN 'idle' THEN 2 ELSE 3 END")
            ->get();

        // Batch-load task counts per agent to avoid N+1
        $agentIds = $agents->pluck('id')->toArray();

        $activeCounts = Task::where('workspace_id', $workspaceId)
            ->whereIn('agent_id', $agentIds)
            ->where('status', Task::STATUS_ACTIVE)
            ->selectRaw('agent_id, count(*) as count')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        $pendingCounts = Task::where('workspace_id', $workspaceId)
            ->whereIn('agent_id', $agentIds)
            ->where('status', Task::STATUS_PENDING)
            ->selectRaw('agent_id, count(*) as count')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        $completedTodayCounts = Task::where('workspace_id', $workspaceId)
            ->whereIn('agent_id', $agentIds)
            ->where('status', Task::STATUS_COMPLETED)
            ->where('completed_at', '>=', $todayStart)
            ->selectRaw('agent_id, count(*) as count')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        $completedWeekCounts = Task::where('workspace_id', $workspaceId)
            ->whereIn('agent_id', $agentIds)
            ->where('status', Task::STATUS_COMPLETED)
            ->where('completed_at', '>=', $weekStart)
            ->selectRaw('agent_id, count(*) as count')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        $failedWeekCounts = Task::where('workspace_id', $workspaceId)
            ->whereIn('agent_id', $agentIds)
            ->where('status', Task::STATUS_FAILED)
            ->where('updated_at', '>=', $weekStart)
            ->selectRaw('agent_id, count(*) as count')
            ->groupBy('agent_id')
            ->pluck('count', 'agent_id');

        // Avg duration for completed tasks this week (in seconds)
        $avgDurations = Task::where('workspace_id', $workspaceId)
            ->whereIn('agent_id', $agentIds)
            ->where('status', Task::STATUS_COMPLETED)
            ->where('completed_at', '>=', $weekStart)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('agent_id, avg(extract(epoch from (completed_at - started_at))) as avg_seconds')
            ->groupBy('agent_id')
            ->pluck('avg_seconds', 'agent_id');

        // Current task title for working agents
        $currentTasks = Task::where('workspace_id', $workspaceId)
            ->whereIn('agent_id', $agentIds)
            ->where('status', Task::STATUS_ACTIVE)
            ->orderBy('started_at', 'desc')
            ->get()
            ->unique('agent_id')
            ->pluck('title', 'agent_id');

        $agentWorkloads = $agents->map(function (User $agent) use (
            $activeCounts, $pendingCounts, $completedTodayCounts,
            $completedWeekCounts, $failedWeekCounts, $avgDurations, $currentTasks
        ) {
            return [
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'avatar' => $agent->avatar,
                    'agentType' => $agent->agent_type,
                    'status' => $agent->status ?? 'offline',
                ],
                'metrics' => [
                    'currentTasks' => $activeCounts->get($agent->id, 0),
                    'pendingTasks' => $pendingCounts->get($agent->id, 0),
                    'completedToday' => $completedTodayCounts->get($agent->id, 0),
                    'completedThisWeek' => $completedWeekCounts->get($agent->id, 0),
                    'failedThisWeek' => $failedWeekCounts->get($agent->id, 0),
                    'avgDurationSeconds' => $avgDurations->get($agent->id) ? round($avgDurations->get($agent->id)) : null,
                ],
                'currentTaskTitle' => $currentTasks->get($agent->id),
            ];
        })->values();

        // Summary
        $activeAgents = $agents->where('status', 'working')->count();

        return response()->json([
            'agents' => $agentWorkloads,
            'summary' => [
                'totalAgents' => $agents->count(),
                'activeAgents' => $activeAgents,
                'totalActiveTasks' => $activeCounts->sum(),
                'totalPendingTasks' => $pendingCounts->sum(),
                'completedToday' => $completedTodayCounts->sum(),
                'completedThisWeek' => $completedWeekCounts->sum(),
                'failedThisWeek' => $failedWeekCounts->sum(),
            ],
        ]);
    }
}
