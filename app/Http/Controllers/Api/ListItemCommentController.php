<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListItemComment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListItemCommentController extends Controller
{
    public function index(string $listItemId): mixed
    {
        return ListItemComment::with(['author', 'replies.author'])
            ->where('list_item_id', $listItemId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function store(Request $request, string $listItemId): mixed
    {
        $comment = ListItemComment::create([
            'id' => Str::uuid()->toString(),
            'list_item_id' => $listItemId,
            'author_id' => $request->input('authorId') ?? auth()->id(),
            'content' => $request->input('content'),
            'parent_id' => $request->input('parentId'),
        ]);

        return $comment->load('author');
    }

    public function destroy(string $listItemId, string $commentId): \Illuminate\Http\JsonResponse
    {
        ListItemComment::where('id', $commentId)
            ->where('list_item_id', $listItemId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
