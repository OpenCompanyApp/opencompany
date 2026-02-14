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
            $results['users'] = User::where('name', 'ilike', "%{$query}%")
                ->orWhere('email', 'ilike', "%{$query}%")
                ->limit(10)
                ->get();
        }

        // Search channels
        if (!$type || $type === 'channels') {
            $results['channels'] = Channel::where('name', 'ilike', "%{$query}%")
                ->orWhere('description', 'ilike', "%{$query}%")
                ->limit(10)
                ->get();
        }

        // Search messages
        if (!$type || $type === 'messages') {
            $results['messages'] = Message::with(['author', 'channel'])
                ->where('content', 'ilike', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        // Search tasks
        if (!$type || $type === 'tasks') {
            $results['tasks'] = Task::with(['agent'])
                ->where('title', 'ilike', "%{$query}%")
                ->orWhere('description', 'ilike', "%{$query}%")
                ->limit(10)
                ->get();
        }

        // Search documents
        if (!$type || $type === 'documents') {
            $results['documents'] = Document::with(['author'])
                ->where('title', 'ilike', "%{$query}%")
                ->orWhere('content', 'ilike', "%{$query}%")
                ->limit(10)
                ->get();
        }

        return response()->json($results);
    }
}
