<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Task;
use App\Models\User;

class WorkspaceStatusService
{
    /**
     * Gather workspace status data.
     *
     * @return array<string, mixed>
     */
    public function gather(string $workspaceId): array
    {
        $agents = User::where('type', 'agent')
            ->where('workspace_id', $workspaceId)
            ->orderByRaw("CASE status WHEN 'working' THEN 1 WHEN 'idle' THEN 2 ELSE 3 END")
            ->get();

        $agentsOnline = $agents->whereIn('status', ['working', 'idle'])->count();

        // Current task for each agent
        $agentList = $agents->map(function (User $agent) {
            $currentTask = Task::where('agent_id', $agent->id)
                ->where('status', Task::STATUS_ACTIVE)
                ->latest('started_at')
                ->value('title');

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'status' => $agent->status ?? 'offline',
                'current_task' => $currentTask,
            ];
        })->all();

        $tasksActive = Task::where('workspace_id', $workspaceId)
            ->where('status', Task::STATUS_ACTIVE)
            ->count();

        $tasksCompleted = Task::where('workspace_id', $workspaceId)
            ->where('status', Task::STATUS_COMPLETED)
            ->count();

        $tasksToday = Task::where('workspace_id', $workspaceId)
            ->where('status', Task::STATUS_COMPLETED)
            ->whereDate('updated_at', today())
            ->count();

        $messagesTotal = Message::whereHas('channel', function ($q) use ($workspaceId) {
            $q->where('workspace_id', $workspaceId);
        })->count();

        $messagesToday = Message::whereHas('channel', function ($q) use ($workspaceId) {
            $q->where('workspace_id', $workspaceId);
        })->whereDate('created_at', today())->count();

        return [
            'agents' => $agentList,
            'agents_online' => $agentsOnline,
            'agents_total' => $agents->count(),
            'tasks_active' => $tasksActive,
            'tasks_completed' => $tasksCompleted,
            'tasks_today' => $tasksToday,
            'messages_total' => $messagesTotal,
            'messages_today' => $messagesToday,
        ];
    }
}
