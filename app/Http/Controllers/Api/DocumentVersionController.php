<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentVersionController extends Controller
{
    /** @return \Illuminate\Database\Eloquent\Collection<int, DocumentVersion> */
    public function index(string $documentId): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentVersion::with('author')
            ->where('document_id', $documentId)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    public function restore(Request $request, string $documentId, string $versionId): Document
    {
        $version = DocumentVersion::where('id', $versionId)
            ->where('document_id', $documentId)
            ->firstOrFail();

        $document = Document::findOrFail($documentId);

        // Save current content as a new version before restoring
        $lastVersion = DocumentVersion::where('document_id', $documentId)
            ->max('version_number') ?? 0;

        DocumentVersion::create([
            'id' => Str::uuid()->toString(),
            'document_id' => $documentId,
            'content' => $document->content,
            'version_number' => $lastVersion + 1,
            'change_description' => 'Auto-saved before restoring version ' . $version->version_number,
            'author_id' => $request->input('authorId', $document->author_id),
        ]);

        // Restore the content
        $document->update(['content' => $version->content]);

        return $document->load(['author', 'permissions.user']);
    }
}
