<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationFacts;
use App\Domain\Authorization\Application\AuthorizationScopes;
use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Workspace-wide search for the command palette.
 *
 * Authorization (GIN-1146, GIN-1199): channels and messages are limited to
 * channels the actor may read (AuthorizationScopes::readableChannels), and
 * documents to those the engine allows for `document.read`, so names and
 * snippets of private conversations and unshared documents never appear.
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly AuthorizationScopes $scopes,
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationFacts $facts,
    ) {}

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
                    $q->whereLike('name', "%{$query}%")
                        ->orWhereLike('email', "%{$query}%");
                })
                ->limit(10)
                ->get();
        }

        // Search channels
        if (!$type || $type === 'channels') {
            $results['channels'] = $this->scopes->readableChannels($request->user(), workspace())
                ->where(function ($q) use ($query) {
                    $q->whereLike('name', "%{$query}%")
                        ->orWhereLike('description', "%{$query}%");
                })
                ->limit(10)
                ->get();
        }

        // Search messages (scoped through workspace channels)
        if (!$type || $type === 'messages') {
            $results['messages'] = Message::with(['author', 'channel'])
                ->whereIn('channel_id', $this->scopes->readableChannels($request->user(), workspace())->select('channels.id'))
                ->whereLike('content', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        // Search tasks
        if (!$type || $type === 'tasks') {
            $results['tasks'] = Task::forWorkspace()->with(['agent'])
                ->where(function ($q) use ($query) {
                    $q->whereLike('title', "%{$query}%")
                        ->orWhereLike('description', "%{$query}%");
                })
                ->limit(10)
                ->get();
        }

        // Search documents
        if (!$type || $type === 'documents') {
            $matches = Document::forWorkspace()->with(['author'])
                ->where(function ($q) use ($query) {
                    $q->whereLike('title', "%{$query}%")
                        ->orWhereLike('content', "%{$query}%");
                })
                ->limit(50)
                ->get();
            $decisions = $this->authorization->decideMany($request->user(), 'document.read', $this->facts->documents(workspace(), $matches), workspace());
            $results['documents'] = $matches
                ->filter(fn (Document $doc): bool => ($decisions[(string) $doc->id] ?? null)?->allowed() === true)
                ->take(10)
                ->values();
        }

        return response()->json($results);
    }
}
