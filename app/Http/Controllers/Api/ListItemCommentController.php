<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListItem;
use App\Models\ListItemComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API for comments on kanban/list items.
 *
 * Comments inherit workspace scope from their parent ListItem, so each action
 * validates the item through forWorkspace() before reading or mutating comments.
 */
class ListItemCommentController extends Controller
{
    public function index(string $listItemId): mixed
    {
        ListItem::forWorkspace()->findOrFail($listItemId);

        // Return only top-level comments; replies are eager-loaded underneath so
        // the client can render threads without a second request.
        return ListItemComment::with(['author', 'replies.author'])
            ->where('list_item_id', $listItemId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function store(Request $request, string $listItemId): mixed
    {
        ListItem::forWorkspace()->findOrFail($listItemId);

        // parentId is optional and represents a threaded reply to another
        // comment on the same list item.
        $comment = ListItemComment::create([
            'id' => Str::uuid()->toString(),
            'list_item_id' => $listItemId,
            'author_id' => auth()->id(),
            'content' => $request->input('content'),
            'parent_id' => $request->input('parentId'),
        ]);

        return $comment->load('author');
    }

    public function destroy(string $listItemId, string $commentId): JsonResponse
    {
        ListItem::forWorkspace()->findOrFail($listItemId);

        // Delete is scoped by both comment ID and parent item so a stale comment
        // ID cannot remove a comment from another item.
        ListItemComment::where('id', $commentId)
            ->where('list_item_id', $listItemId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
