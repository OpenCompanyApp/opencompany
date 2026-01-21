<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentAttachmentController extends Controller
{
    public function index(string $documentId)
    {
        return DocumentAttachment::with('uploader')
            ->where('document_id', $documentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request, string $documentId)
    {
        $file = $request->file('file');
        $path = $file->store('document-attachments', 'public');

        $attachment = DocumentAttachment::create([
            'id' => Str::uuid()->toString(),
            'document_id' => $documentId,
            'uploader_id' => $request->input('uploaderId'),
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => '/storage/' . $path,
        ]);

        return $attachment->load('uploader');
    }

    public function destroy(string $documentId, string $attachmentId)
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
