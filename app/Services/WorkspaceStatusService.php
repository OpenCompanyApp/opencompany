<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Channel;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Services\Memory\ContextBudget;
use Illuminate\Support\Collection;

/**
 * Builds the lightweight workspace health snapshot used by chat and slash-command status surfaces.
 *
 * This service only reads workspace-scoped operational data. It does not mutate agent state, trigger
 * queue work, or expose message contents; callers are responsible for authorizing access to the
 * workspace before passing a workspace id in.
 */
class WorkspaceStatusService
{
    public function __construct(private ContextBudget $contextBudget) {}

    /**
     * Gather status counters and current agent activity for one workspace.
     *
     * @return array<string, mixed>
     */
    public function gather(string $workspaceId, ?string $channelId = null, ?string $agentId = null): array
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

        $tasksFailed = Task::where('workspace_id', $workspaceId)
            ->where('status', Task::STATUS_FAILED)
            ->count();

        $pendingApprovals = ApprovalRequest::where('status', 'pending')
            ->whereHas('channel', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            })
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
            'tasks_failed' => $tasksFailed,
            'pending_approvals' => $pendingApprovals,
            'messages_total' => $messagesTotal,
            'messages_today' => $messagesToday,
            'conversation' => $this->conversationStatus($workspaceId, $channelId, $agentId),
        ];
    }

    /**
     * Build the chat-local status payload for the composer status panel.
     *
     * The payload intentionally avoids message contents. It exposes only token
     * counts, run/task identifiers, and compaction metadata so the UI can answer
     * "how much room is left?" without leaking conversation text into generic
     * workspace status logs or unrelated consumers.
     *
     * @return array<string, mixed>|null
     */
    private function conversationStatus(string $workspaceId, ?string $channelId, ?string $agentId): ?array
    {
        if (! $channelId && ! $agentId) {
            return null;
        }

        $channel = $channelId
            ? Channel::where('workspace_id', $workspaceId)->find($channelId)
            : null;

        if ($channelId && ! $channel) {
            return null;
        }

        $agent = $agentId
            ? User::where('type', 'agent')->where('workspace_id', $workspaceId)->find($agentId)
            : null;

        if (! $agent && $channel) {
            $agent = $channel->users()
                ->where('users.type', 'agent')
                ->where('users.workspace_id', $workspaceId)
                ->first();
        }

        $latestTask = Task::where('workspace_id', $workspaceId)
            ->when($channel, fn ($query) => $query->where('channel_id', $channel->id))
            ->when($agent, fn ($query) => $query->where('agent_id', $agent->id))
            ->where('source', Task::SOURCE_CHAT)
            ->latest('updated_at')
            ->first();

        $messagesCount = $channel
            ? Message::where('channel_id', $channel->id)->count()
            : 0;

        $summary = ($channel && $agent)
            ? ConversationSummary::where('workspace_id', $workspaceId)
                ->where('channel_id', $channel->id)
                ->where('agent_id', $agent->id)
                ->first()
            : null;

        $budget = null;
        $messagesInBudget = 0;

        if ($channel && $agent) {
            $query = Message::where('channel_id', $channel->id)
                ->orderBy('created_at', 'asc');

            if ($summary?->last_message_id) {
                $lastSummarizedMessage = Message::find($summary->last_message_id);
                if ($lastSummarizedMessage) {
                    $query->where('created_at', '>', $lastSummarizedMessage->created_at);
                }
            }

            $recentMessages = $query->get(['content']);
            $messagesInBudget = $recentMessages->count();

            $budgetMessages = new Collection;
            if ($summary?->summary) {
                $budgetMessages->push((object) ['content' => $summary->summary]);
            }
            $budgetMessages = $budgetMessages->concat($recentMessages);

            try {
                $budget = $this->contextBudget->snapshotForAgent($agent, $budgetMessages);
            } catch (\Throwable) {
                $budget = null;
            }
        }

        return [
            'channel_id' => $channel?->id,
            'agent_id' => $agent?->id,
            'provider' => $budget['provider'] ?? null,
            'model' => $budget['model'] ?? $agent?->brain,
            'context' => $budget,
            'messages_total' => $messagesCount,
            'messages_in_budget' => $messagesInBudget,
            'summary' => $summary ? [
                'tokens_after' => $summary->tokens_after,
                'messages_summarized' => $summary->messages_summarized,
                'compaction_count' => $summary->compaction_count,
                'last_compacted_at' => $summary->updated_at?->toIso8601String(),
                'circuit_open_until' => $summary->compaction_circuit_open_until?->toIso8601String(),
                'last_error' => $summary->last_compaction_error,
            ] : null,
            'last_run' => $latestTask ? [
                'id' => $latestTask->id,
                'title' => $latestTask->title,
                'status' => $latestTask->status,
                'started_at' => $latestTask->started_at?->toIso8601String(),
                'completed_at' => $latestTask->completed_at?->toIso8601String(),
                'updated_at' => $latestTask->updated_at?->toIso8601String(),
                'token_breakdown' => $latestTask->context['token_breakdown'] ?? null,
                'result' => [
                    'prompt_tokens' => $latestTask->result['prompt_tokens'] ?? null,
                    'completion_tokens' => $latestTask->result['completion_tokens'] ?? null,
                    'tool_calls_count' => $latestTask->result['tool_calls_count'] ?? null,
                    'generation_time_ms' => $latestTask->result['generation_time_ms'] ?? null,
                ],
            ] : null,
        ];
    }
}
