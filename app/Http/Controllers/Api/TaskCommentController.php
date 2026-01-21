<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskCommentController extends Controller
{
    public function index(string $taskId)
    {
        return TaskComment::with(['author', 'parent'])
            ->where('task_id', $taskId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function store(Request $request, string $taskId)
    {
        $comment = TaskComment::create([
            'id' => Str::uuid()->toString(),
            'task_id' => $taskId,
            'author_id' => $request->input('authorId'),
            'content' => $request->input('content'),
            'parent_id' => $request->input('parentId'),
        ]);

        return $comment->load('author');
    }

    public function destroy(string $taskId, string $commentId)
    {
        TaskComment::where('id', $commentId)
            ->where('task_id', $taskId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
