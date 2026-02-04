<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;

class StatsController extends Controller
{
    public function index()
    {
        // Calculate dynamic stats
        $agentsOnline = User::where('type', 'agent')
            ->whereIn('status', ['working', 'idle'])
            ->count();

        $totalAgents = User::where('type', 'agent')->count();

        $tasksCompleted = Task::where('status', 'completed')->count();
        $tasksToday = Task::where('status', 'completed')
            ->whereDate('updated_at', today())
            ->count();

        $messagesTotal = Message::count();
        $messagesToday = Message::whereDate('created_at', today())->count();

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
