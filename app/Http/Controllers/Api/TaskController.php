<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExecuteAgentTaskJob;
use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TaskController - Manages discrete work items (cases) that agents work on
 *
 * Not to be confused with ListItemController which handles kanban board items.
 */
class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::with(['agent', 'requester', 'channel', 'steps']);

        // Filter by status
        if ($request->has('status')) {
            $statuses = is_array($request->input('status'))
                ? $request->input('status')
                : [$request->input('status')];
            $query->whereIn('status', $statuses);
        }

        // Filter by agent
        if ($request->has('agentId')) {
            $query->where('agent_id', $request->input('agentId'));
        }

        // Filter by requester
        if ($request->has('requesterId')) {
            $query->where('requester_id', $request->input('requesterId'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // Filter by source
        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        // Search by title or description
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Order by
        $orderBy = $request->input('orderBy', 'created_at');
        $orderDir = $request->input('orderDir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        $perPage = (int) $request->input('perPage', 50);
        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function ($task) {
            $task->makeHidden(['context']);

            return $task;
        });

        return array_merge($paginated->toArray(), [
            'counts' => [
                'total' => Task::count(),
                'active' => Task::where('status', 'active')->count(),
                'completed' => Task::where('status', 'completed')->count(),
            ],
        ]);
    }

    public function show(string $id): Task
    {
        return Task::with([
            'agent',
            'requester',
            'channel',
            'listItem',
            'parentTask',
            'subtasks.agent',
            'steps',
        ])->findOrFail($id);
    }

    public function store(Request $request): Task
    {
        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'type' => $request->input('type', Task::TYPE_CUSTOM),
            'status' => Task::STATUS_PENDING,
            'priority' => $request->input('priority', Task::PRIORITY_NORMAL),
            'agent_id' => $request->input('agentId'),
            'requester_id' => $request->input('requesterId'),
            'channel_id' => $request->input('channelId'),
            'list_item_id' => $request->input('listItemId'),
            'parent_task_id' => $request->input('parentTaskId'),
            'source' => $request->input('source', Task::SOURCE_MANUAL),
            'context' => $request->input('context'),
            'due_at' => $request->input('dueAt'),
        ]);

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function update(Request $request, string $id): Task
    {
        $task = Task::findOrFail($id);

        $data = $request->only([
            'title',
            'description',
            'type',
            'priority',
            'context',
            'result',
        ]);

        // Handle camelCase to snake_case conversion
        if ($request->has('agentId')) {
            $data['agent_id'] = $request->input('agentId');
        }
        if ($request->has('channelId')) {
            $data['channel_id'] = $request->input('channelId');
        }
        if ($request->has('dueAt')) {
            $data['due_at'] = $request->input('dueAt');
        }

        $task->update($data);

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function destroy(string $id): JsonResponse
    {
        Task::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    // Lifecycle Actions

    public function start(string $id): Task
    {
        $task = Task::findOrFail($id);

        // If task has an assigned agent, dispatch the execution job
        if ($task->agent_id) {
            ExecuteAgentTaskJob::dispatch($task);
            // Return immediately — task will be updated via broadcast
            return $task->load(['agent', 'requester', 'channel', 'steps']);
        }

        // No agent assigned — just start the task manually
        $task->start();

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function pause(string $id): Task
    {
        $task = Task::findOrFail($id);
        $task->pause();

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function resume(string $id): Task
    {
        $task = Task::findOrFail($id);
        $task->resume();

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function complete(Request $request, string $id): Task
    {
        $task = Task::findOrFail($id);
        $task->complete($request->input('result'));

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function fail(Request $request, string $id): Task
    {
        $task = Task::findOrFail($id);
        $task->fail($request->input('reason'));

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    public function cancel(string $id): Task
    {
        $task = Task::findOrFail($id);
        $task->cancel();

        return $task->load(['agent', 'requester', 'channel', 'steps']);
    }

    // Task Steps

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TaskStep>
     */
    public function steps(string $id)
    {
        $task = Task::findOrFail($id);

        return $task->steps()->orderBy('created_at')->get();
    }

    public function addStep(Request $request, string $id): TaskStep
    {
        $task = Task::findOrFail($id);

        $step = $task->addStep(
            $request->input('description'),
            $request->input('type', TaskStep::TYPE_ACTION),
            $request->input('metadata', [])
        );

        return $step;
    }

    public function updateStep(Request $request, string $taskId, string $stepId): TaskStep
    {
        $step = TaskStep::where('task_id', $taskId)
            ->where('id', $stepId)
            ->firstOrFail();

        $data = $request->only(['description', 'metadata']);

        if ($request->has('stepType')) {
            $data['step_type'] = $request->input('stepType');
        }

        $step->update($data);

        return $step;
    }

    public function completeStep(string $taskId, string $stepId): TaskStep
    {
        $step = TaskStep::where('task_id', $taskId)
            ->where('id', $stepId)
            ->firstOrFail();

        $step->complete();

        return $step;
    }
}
