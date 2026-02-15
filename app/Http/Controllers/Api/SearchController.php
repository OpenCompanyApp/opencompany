<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Document;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $request->input('q', '');
        $type = $request->input('type'); // optional: users, channels, messages, tasks, documents

        if (empty($query)) {
            return response()->json([
                'users' => [],
                'channels' => [],
                'messages' => [],
                'tasks' => [],
                'documents' => [],
            ]);
        }

        $results = [];

        // Search users
        if (!$type || $type === 'users') {
            $results['users'] = User::where('workspace_id', workspace()->id)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                        ->orWhere('email', 'ilike', "%{$query}%");
                })
                ->limit(10)
                ->get();
        }

        // Search channels
        if (!$type || $type === 'channels') {
            $results['channels'] = Channel::forWorkspace()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                        ->orWhere('description', 'ilike', "%{$query}%");
                })
                ->limit(10)
                ->get();
        }

        // Search messages (scoped through workspace channels)
        if (!$type || $type === 'messages') {
            $results['messages'] = Message::with(['author', 'channel'])
                ->whereHas('channel', function ($q) {
                    $q->where('workspace_id', workspace()->id);
                })
                ->where('content', 'ilike', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        // Search tasks
        if (!$type || $type === 'tasks') {
            $results['tasks'] = Task::forWorkspace()->with(['agent'])
                ->where(function ($q) use ($query) {
                    $q->where('title', 'ilike', "%{$query}%")
                        ->orWhere('description', 'ilike', "%{$query}%");
                })
                ->limit(10)
                ->get();
        }

        // Search documents
        if (!$type || $type === 'documents') {
            $results['documents'] = Document::forWorkspace()->with(['author'])
                ->where(function ($q) use ($query) {
                    $q->where('title', 'ilike', "%{$query}%")
                        ->orWhere('content', 'ilike', "%{$query}%");
                })
                ->limit(10)
                ->get();
        }

        return response()->json($results);
    }
}
