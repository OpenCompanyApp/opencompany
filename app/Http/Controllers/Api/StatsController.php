<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;

class StatsController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        // Calculate dynamic stats
        $agentsOnline = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->whereIn('status', ['working', 'idle'])
            ->count();

        $totalAgents = User::where('type', 'agent')
            ->where('workspace_id', workspace()->id)
            ->count();

        $tasksCompleted = Task::forWorkspace()->where('status', 'completed')->count();
        $tasksToday = Task::forWorkspace()->where('status', 'completed')
            ->whereDate('updated_at', today())
            ->count();

        $messagesTotal = Message::whereHas('channel', function ($q) {
            $q->where('workspace_id', workspace()->id);
        })->count();
        $messagesToday = Message::whereHas('channel', function ($q) {
            $q->where('workspace_id', workspace()->id);
        })->whereDate('created_at', today())->count();

        // Return all stats in camelCase format as expected by the frontend
        return response()->json([
            'agentsOnline' => $agentsOnline,
            'totalAgents' => $totalAgents,
            'tasksCompleted' => $tasksCompleted,
            'tasksToday' => $tasksToday,
            'messagesTotal' => $messagesTotal,
            'messagesToday' => $messagesToday,
        ]);
    }
}
