<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Controllers\Concerns\AuthorizesDocumentAccess;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentComment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Document comments. Reading needs `document.read`, commenting
 * `document.comment` (readers with write access), and editing, resolving or
 * deleting a comment `document.comment.manage` (its author or a document
 * editor). See AuthorizesDocumentAccess.
 */
class DocumentCommentController extends Controller
{
    use AuthorizesDocumentAccess;

    /** @return Collection<int, DocumentComment> */
    public function index(Request $request, string $documentId): Collection
    {
        $this->authorizedDocument($request, $documentId, 'document.read');

        return DocumentComment::with(['author', 'parent', 'resolvedBy'])
            ->where('document_id', $documentId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function store(Request $request, string $documentId): DocumentComment
    {
        $this->authorizedDocument($request, $documentId, 'document.comment');

        // A reply must belong to the same document.
        $parentId = $request->input('parentId');
        if ($parentId !== null && ! DocumentComment::where('document_id', $documentId)->whereKey($parentId)->exists()) {
            abort(422, 'The comment replied to is not on this document.');
        }

        $comment = DocumentComment::create([
            'id' => Str::uuid()->toString(),
            'document_id' => $documentId,
            'author_id' => auth()->id(),
            'content' => $request->input('content'),
            'parent_id' => $parentId,
        ]);

        return $comment->load('author');
    }

    public function update(Request $request, string $documentId, string $commentId): DocumentComment
    {
        $this->authorizedDocument($request, $documentId, 'document.read');

        $comment = DocumentComment::where('id', $commentId)
            ->where('document_id', $documentId)
            ->firstOrFail();
        app(AuthorizationService::class)->authorize($request->user(), 'document.comment.manage', $comment);

        $data = [];

        if ($request->has('content')) {
            $data['content'] = $request->input('content');
        }
        if ($request->has('resolved')) {
            $data['resolved'] = $request->input('resolved');
            if ($request->input('resolved')) {
                $data['resolved_at'] = now();
                $data['resolved_by_id'] = auth()->id();
            } else {
                $data['resolved_at'] = null;
                $data['resolved_by_id'] = null;
            }
        }

        $comment->update($data);

        return $comment->load(['author', 'resolvedBy']);
    }

    public function destroy(Request $request, string $documentId, string $commentId): JsonResponse
    {
        $this->authorizedDocument($request, $documentId, 'document.read');

        $comment = DocumentComment::where('id', $commentId)
            ->where('document_id', $documentId)
            ->firstOrFail();
        app(AuthorizationService::class)->authorize($request->user(), 'document.comment.manage', $comment);
        $comment->delete();

        return response()->json(['success' => true]);
    }
}
