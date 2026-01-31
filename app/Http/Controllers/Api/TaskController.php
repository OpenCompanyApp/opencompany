<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskCollaborator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function index()
    {
        return Task::with(['assignee', 'creator', 'collaborators', 'channel'])
            ->orderBy('position')
            ->get();
    }

    public function show(string $id)
    {
        return Task::with(['assignee', 'creator', 'collaborators', 'channel', 'comments.author'])
            ->findOrFail($id);
    }

    public function store(Request $request)
    {
        $isFolder = $request->input('isFolder', false);
        $parentId = $request->input('parentId');

        // For folders, calculate max position among siblings
        // For tasks, calculate max position within parent and status
        if ($isFolder) {
            $maxPosition = Task::where('parent_id', $parentId)
                ->where('is_folder', true)
                ->max('position') ?? 0;
        } else {
            $maxPosition = Task::where('parent_id', $parentId)
                ->where('is_folder', false)
                ->where('status', $request->input('status', 'backlog'))
                ->max('position') ?? 0;
        }

        $task = Task::create([
            'id' => Str::uuid()->toString(),
            'parent_id' => $parentId,
            'is_folder' => $isFolder,
            'title' => $request->input('title'),
            'description' => $request->input('description', ''),
            'status' => $isFolder ? 'backlog' : $request->input('status', 'backlog'),
            'priority' => $isFolder ? 'medium' : $request->input('priority', 'medium'),
            'assignee_id' => $request->input('assigneeId'),
            'creator_id' => $request->input('creatorId'),
            'channel_id' => $request->input('channelId'),
            'estimated_cost' => $request->input('estimatedCost'),
            'position' => $maxPosition + 1,
        ]);

        // Add collaborators
        if ($request->input('collaboratorIds')) {
            foreach ($request->input('collaboratorIds') as $collaboratorId) {
                TaskCollaborator::create([
                    'id' => Str::uuid()->toString(),
                    'task_id' => $task->id,
                    'user_id' => $collaboratorId,
                ]);
            }
        }

        return $task->load(['assignee', 'creator', 'collaborators', 'channel']);
    }

    public function update(Request $request, string $id)
    {
        $task = Task::findOrFail($id);

        $data = $request->only([
            'title',
            'description',
            'status',
            'priority',
            'position',
            'progress',
        ]);

        // Handle snake_case conversion for camelCase inputs
        if ($request->has('parentId')) {
            $data['parent_id'] = $request->input('parentId');
        }
        if ($request->has('assigneeId')) {
            $data['assignee_id'] = $request->input('assigneeId');
        }
        if ($request->has('channelId')) {
            $data['channel_id'] = $request->input('channelId');
        }
        if ($request->has('estimatedCost')) {
            $data['estimated_cost'] = $request->input('estimatedCost');
        }
        if ($request->has('actualCost')) {
            $data['actual_cost'] = $request->input('actualCost');
        }
        if ($request->has('dueDate')) {
            $data['due_date'] = $request->input('dueDate');
        }

        // Handle status changes
        if (isset($data['status'])) {
            if ($data['status'] === 'in_progress' && !$task->started_at) {
                $data['started_at'] = now();
            }
            if ($data['status'] === 'completed' && !$task->completed_at) {
                $data['completed_at'] = now();
            }
        }

        $task->update($data);

        // Update collaborators if provided
        if ($request->has('collaboratorIds')) {
            TaskCollaborator::where('task_id', $id)->delete();
            foreach ($request->input('collaboratorIds') as $collaboratorId) {
                TaskCollaborator::create([
                    'id' => Str::uuid()->toString(),
                    'task_id' => $id,
                    'user_id' => $collaboratorId,
                ]);
            }
        }

        return $task->load(['assignee', 'creator', 'collaborators', 'channel']);
    }

    public function destroy(string $id)
    {
        Task::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request)
    {
        $taskOrders = $request->input('taskOrders');

        foreach ($taskOrders as $order) {
            $data = ['position' => $order['position']];
            if (isset($order['status'])) {
                $data['status'] = $order['status'];
            }
            Task::where('id', $order['id'])->update($data);
        }

        return response()->json(['success' => true]);
    }
}
