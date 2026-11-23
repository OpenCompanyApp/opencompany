<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesDocumentAccess;
use App\Http\Controllers\Controller;
use App\Models\DocumentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Document attachments: listing needs `document.read`, uploading and
 * deleting `document.write` (GIN-1199).
 */
class DocumentAttachmentController extends Controller
{
    use AuthorizesDocumentAccess;

    public function index(Request $request, string $documentId): mixed
    {
        $this->authorizedDocument($request, $documentId, 'document.read');

        return DocumentAttachment::with('uploadedBy')
            ->where('document_id', $documentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request, string $documentId): mixed
    {
        $this->authorizedDocument($request, $documentId, 'document.write');

        $file = $request->file('file');
        $workspaceId = workspace()->id;
        $path = $file->store("{$workspaceId}/document-attachments", 'public');

        $attachment = DocumentAttachment::create([
            'id' => Str::uuid()->toString(),
            'document_id' => $documentId,
            'uploaded_by_id' => auth()->id(),
            'filename' => $file->hashName(),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => '/storage/' . $path,
        ]);

        return $attachment->load('uploadedBy');
    }

    public function destroy(Request $request, string $documentId, string $attachmentId): \Illuminate\Http\JsonResponse
    {
        $this->authorizedDocument($request, $documentId, 'document.write');

        $attachment = DocumentAttachment::where('id', $attachmentId)
            ->where('document_id', $documentId)
            ->firstOrFail();

        // Delete file from storage
        $path = str_replace('/storage/', '', $attachment->url);
        Storage::disk('public')->delete($path);

        $attachment->delete();

        return response()->json(['success' => true]);
    }
}
