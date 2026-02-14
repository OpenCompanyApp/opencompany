<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /** @return \Illuminate\Support\Collection<int, mixed> */
    public function index(Request $request): \Illuminate\Support\Collection
    {
        $limit = (int) $request->input('limit', 50);

        // 1. Task activities (completed, started, failed)
        $tasks = Task::with('agent')
            ->whereIn('status', ['completed', 'active', 'failed'])
            ->whereNotNull('started_at')
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $taskActivities */
        $taskActivities = $tasks->map(function (Task $task) {
            $type = match ($task->status) {
                'completed' => 'task_completed',
                'failed' => 'error',
                default => 'task_started',
            };
            $timestamp = match ($task->status) {
                'completed' => $task->completed_at,
                'failed' => $task->updated_at,
                default => $task->started_at,
            };
            $description = match ($task->status) {
                'completed' => "Completed task: {$task->title}",
                'failed' => "Task failed: {$task->title}",
                default => "Started working on: {$task->title}",
            };

            return [
                'id' => "task-{$task->id}",
                'type' => $type,
                'description' => $description,
                'actor' => $task->agent,
                'actor_id' => $task->agent_id,
                'timestamp' => $timestamp,
                'metadata' => ['taskTitle' => $task->title],
            ];
        });

        // 2. Message activities
        $messages = Message::with(['author', 'channel'])
            ->latest('timestamp')
            ->limit($limit)
            ->get();

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $messageActivities */
        $messageActivities = $messages->map(function (Message $msg) {
            /** @var \App\Models\Channel|null $msgChannel */
            $msgChannel = $msg->channel;
            $channelName = $msgChannel->name ?? 'unknown';

            return [
                'id' => "msg-{$msg->id}",
                'type' => 'message',
                'description' => "Sent message in #{$channelName}",
                'actor' => $msg->author,
                'actor_id' => $msg->author_id,
                'timestamp' => $msg->timestamp,
                'metadata' => ['channelName' => $channelName],
            ];
        });

        // 3. Approval activities
        $approvals = ApprovalRequest::with(['requester', 'respondedBy'])
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $approvalActivities = $approvals->flatMap(function (ApprovalRequest $a) {
            $items = collect();

            $items->push([
                'id' => "approval-req-{$a->id}",
                'type' => 'approval_needed',
                'description' => "Approval request: {$a->title}",
                'actor' => $a->requester,
                'actor_id' => $a->requester_id,
                'timestamp' => $a->created_at,
                'metadata' => array_filter([
                    'amount' => $a->amount,
                ]),
            ]);

            if ($a->responded_at && $a->respondedBy) {
                $items->push([
                    'id' => "approval-res-{$a->id}",
                    'type' => 'approval_granted',
                    'description' => "Approved: {$a->title}",
                    'actor' => $a->respondedBy,
                    'actor_id' => $a->responded_by_id,
                    'timestamp' => $a->responded_at,
                    'metadata' => array_filter([
                        'amount' => $a->amount,
                    ]),
                ]);
            }

            return $items;
        });

        // 4. Agent spawned (agent user creation)
        $agents = User::where('type', 'agent')
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $agentActivities = $agents->map(fn (User $agent) => [
            'id' => "agent-{$agent->id}",
            'type' => 'agent_spawned',
            'description' => "Agent {$agent->name} was created",
            'actor' => $agent,
            'actor_id' => $agent->id,
            'timestamp' => $agent->created_at,
            'metadata' => [],
        ]);

        // Merge, sort, limit
        return $taskActivities
            ->concat($messageActivities)
            ->concat($approvalActivities)
            ->concat($agentActivities)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values();
    }
}
