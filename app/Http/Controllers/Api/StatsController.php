<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Message;
use App\Models\Stat;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index()
    {
        // Get the credits stats
        $creditStats = Stat::firstOrCreate(
            ['id' => 'global'],
            ['credits_used' => 0, 'credits_remaining' => 1000]
        );

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
            'creditsUsed' => (float) $creditStats->credits_used,
            'creditsRemaining' => (float) $creditStats->credits_remaining,
        ]);
    }

    public function update(Request $request)
    {
        $stats = Stat::firstOrCreate(
            ['id' => 'global'],
            ['credits_used' => 0, 'credits_remaining' => 1000]
        );

        $stats->update($request->only([
            'credits_used',
            'credits_remaining',
        ]));

        // Return updated stats in the same format
        return $this->index();
    }
}
