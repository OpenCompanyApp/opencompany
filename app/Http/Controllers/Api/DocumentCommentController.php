<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentComment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentCommentController extends Controller
{
    public function index(string $documentId)
    {
        return DocumentComment::with(['author', 'parent', 'resolvedBy'])
            ->where('document_id', $documentId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function store(Request $request, string $documentId)
    {
        $comment = DocumentComment::create([
            'id' => Str::uuid()->toString(),
            'document_id' => $documentId,
            'author_id' => $request->input('authorId'),
            'content' => $request->input('content'),
            'parent_id' => $request->input('parentId'),
        ]);

        return $comment->load('author');
    }

    public function update(Request $request, string $documentId, string $commentId)
    {
        $comment = DocumentComment::where('id', $commentId)
            ->where('document_id', $documentId)
            ->firstOrFail();

        $data = [];

        if ($request->has('content')) {
            $data['content'] = $request->input('content');
        }
        if ($request->has('resolved')) {
            $data['resolved'] = $request->input('resolved');
            if ($request->input('resolved')) {
                $data['resolved_at'] = now();
                $data['resolved_by_id'] = $request->input('resolvedById');
            } else {
                $data['resolved_at'] = null;
                $data['resolved_by_id'] = null;
            }
        }

        $comment->update($data);

        return $comment->load(['author', 'resolvedBy']);
    }

    public function destroy(string $documentId, string $commentId)
    {
        DocumentComment::where('id', $commentId)
            ->where('document_id', $documentId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
