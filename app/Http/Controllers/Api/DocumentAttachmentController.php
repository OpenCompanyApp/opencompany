<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentAttachmentController extends Controller
{
    public function index(string $documentId): mixed
    {
        return DocumentAttachment::with('uploadedBy')
            ->where('document_id', $documentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request, string $documentId): mixed
    {
        $file = $request->file('file');
        $path = $file->store('document-attachments', 'public');

        $attachment = DocumentAttachment::create([
            'id' => Str::uuid()->toString(),
            'document_id' => $documentId,
            'uploaded_by_id' => $request->input('uploaderId'),
            'filename' => $file->hashName(),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => '/storage/' . $path,
        ]);

        return $attachment->load('uploadedBy');
    }

    public function destroy(string $documentId, string $attachmentId): \Illuminate\Http\JsonResponse
    {
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
