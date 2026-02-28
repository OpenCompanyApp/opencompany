<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 50), 200);
        $offset = (int) $request->input('offset', 0);
        $typeFilter = $request->input('type');
        $userIdFilter = $request->input('userId');
        $sinceFilter = $request->input('since'); // ISO date string

        $sinceDate = $sinceFilter ? Carbon::parse($sinceFilter)->startOfDay() : null;

        $activities = collect();

        // Determine which source types to query based on filter
        $queryTypes = $typeFilter
            ? $this->mapFilterToQueryTypes($typeFilter)
            : ['tasks', 'messages', 'approvals', 'agents'];

        // 1. Task activities (completed, started, failed)
        if (in_array('tasks', $queryTypes)) {
            $query = Task::forWorkspace()->with('agent')
                ->whereIn('status', ['completed', 'active', 'failed'])
                ->whereNotNull('started_at')
                ->latest('updated_at');

            if ($userIdFilter) {
                $query->where('agent_id', $userIdFilter);
            }
            if ($sinceDate) {
                $query->where('updated_at', '>=', $sinceDate);
            }

            $tasks = $query->limit($limit + $offset)->get();

            $taskActivities = $tasks->flatMap(function (Task $task) use ($typeFilter) {
                $items = collect();

                // A task can produce multiple activity entries (started + completed/failed)
                if ($task->status === 'completed') {
                    if (!$typeFilter || $typeFilter === 'task_completed') {
                        $items->push($this->buildTaskActivity($task, 'task_completed'));
                    }
                    // Also show the "started" event if not filtering by type
                    if (!$typeFilter) {
                        $items->push($this->buildTaskActivity($task, 'task_started'));
                    }
                } elseif ($task->status === 'failed') {
                    if (!$typeFilter || $typeFilter === 'task_failed') {
                        $items->push($this->buildTaskActivity($task, 'task_failed'));
                    }
                } else {
                    if (!$typeFilter || $typeFilter === 'task_started') {
                        $items->push($this->buildTaskActivity($task, 'task_started'));
                    }
                }

                return $items;
            });

            $activities = $activities->concat($taskActivities);
        }

        // 2. Message activities
        if (in_array('messages', $queryTypes)) {
            $query = Message::with(['author', 'channel'])
                ->whereHas('channel', fn ($q) => $q->where('workspace_id', workspace()->id))
                ->latest('timestamp');

            if ($userIdFilter) {
                $query->where('author_id', $userIdFilter);
            }
            if ($sinceDate) {
                $query->where('timestamp', '>=', $sinceDate);
            }

            $messages = $query->limit($limit + $offset)->get();

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
                    'metadata' => ['channelName' => $channelName, 'channelId' => $msgChannel->id ?? null],
                    'url' => $msgChannel ? "/chat/{$msgChannel->id}" : null,
                ];
            });

            $activities = $activities->concat($messageActivities);
        }

        // 3. Approval activities
        if (in_array('approvals', $queryTypes)) {
            $query = ApprovalRequest::with(['requester', 'respondedBy'])
                ->whereHas('requester', fn ($q) => $q->where('workspace_id', workspace()->id))
                ->latest('created_at');

            if ($userIdFilter) {
                $query->where(function ($q) use ($userIdFilter) {
                    $q->where('requester_id', $userIdFilter)
                      ->orWhere('responded_by_id', $userIdFilter);
                });
            }
            if ($sinceDate) {
                $query->where('created_at', '>=', $sinceDate);
            }

            $approvals = $query->limit($limit + $offset)->get();

            $approvalActivities = $approvals->flatMap(function (ApprovalRequest $a) use ($typeFilter) {
                $items = collect();

                if (!$typeFilter || $typeFilter === 'approval_needed') {
                    $items->push([
                        'id' => "approval-req-{$a->id}",
                        'type' => 'approval_needed',
                        'description' => "Approval request: {$a->title}",
                        'actor' => $a->requester,
                        'actor_id' => $a->requester_id,
                        'timestamp' => $a->created_at,
                        'metadata' => array_filter(['amount' => $a->amount]),
                        'url' => "/approvals/{$a->id}",
                    ]);
                }

                if ($a->responded_at && $a->respondedBy && (!$typeFilter || $typeFilter === 'approval_granted')) {
                    $items->push([
                        'id' => "approval-res-{$a->id}",
                        'type' => 'approval_granted',
                        'description' => "Approved: {$a->title}",
                        'actor' => $a->respondedBy,
                        'actor_id' => $a->responded_by_id,
                        'timestamp' => $a->responded_at,
                        'metadata' => array_filter(['amount' => $a->amount]),
                        'url' => "/approvals/{$a->id}",
                    ]);
                }

                return $items;
            });

            $activities = $activities->concat($approvalActivities);
        }

        // 4. Agent spawned
        if (in_array('agents', $queryTypes)) {
            $query = User::where('type', 'agent')
                ->where('workspace_id', workspace()->id)
                ->latest('created_at');

            if ($userIdFilter) {
                $query->where('id', $userIdFilter);
            }
            if ($sinceDate) {
                $query->where('created_at', '>=', $sinceDate);
            }

            $agents = $query->limit($limit + $offset)->get();

            $agentActivities = $agents->map(fn (User $agent) => [
                'id' => "agent-{$agent->id}",
                'type' => 'agent_spawned',
                'description' => "Agent {$agent->name} was created",
                'actor' => $agent,
                'actor_id' => $agent->id,
                'timestamp' => $agent->created_at,
                'metadata' => [],
                'url' => "/agent/{$agent->id}",
            ]);

            $activities = $activities->concat($agentActivities);
        }

        // Sort, paginate, and return
        $sorted = $activities->sortByDesc('timestamp')->values();
        $total = $sorted->count();
        $page = $sorted->slice($offset, $limit)->values();

        return response()->json([
            'data' => $page,
            'total' => $total,
            'hasMore' => ($offset + $limit) < $total,
        ]);
    }

    private function buildTaskActivity(Task $task, string $type): array
    {
        $timestamp = match ($type) {
            'task_completed' => $task->completed_at,
            'task_failed' => $task->updated_at,
            default => $task->started_at,
        };
        $description = match ($type) {
            'task_completed' => "Completed task: {$task->title}",
            'task_failed' => "Task failed: {$task->title}",
            default => "Started working on: {$task->title}",
        };

        return [
            'id' => "task-{$type}-{$task->id}",
            'type' => $type,
            'description' => $description,
            'actor' => $task->agent,
            'actor_id' => $task->agent_id,
            'timestamp' => $timestamp,
            'metadata' => ['taskTitle' => $task->title, 'taskId' => $task->id],
            'url' => "/tasks/{$task->id}",
        ];
    }

    /**
     * Map a frontend type filter to the source query types needed.
     *
     * @return list<string>
     */
    private function mapFilterToQueryTypes(string $type): array
    {
        return match ($type) {
            'task_completed', 'task_started', 'task_failed' => ['tasks'],
            'message' => ['messages'],
            'approval_needed', 'approval_granted' => ['approvals'],
            'agent_spawned' => ['agents'],
            default => ['tasks', 'messages', 'approvals', 'agents'],
        };
    }
}
