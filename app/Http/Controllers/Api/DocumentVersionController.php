<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesDocumentAccess;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Document version history: listing needs `document.read`, restoring
 * `document.write` (GIN-1199).
 */
class DocumentVersionController extends Controller
{
    use AuthorizesDocumentAccess;

    /** @return \Illuminate\Database\Eloquent\Collection<int, DocumentVersion> */
    public function index(Request $request, string $documentId): \Illuminate\Database\Eloquent\Collection
    {
        $this->authorizedDocument($request, $documentId, 'document.read');

        return DocumentVersion::with('author')
            ->where('document_id', $documentId)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    public function restore(Request $request, string $documentId, string $versionId): Document
    {
        $document = $this->authorizedDocument($request, $documentId, 'document.write');

        $version = DocumentVersion::where('id', $versionId)
            ->where('document_id', $documentId)
            ->firstOrFail();

        // Save current content as a new version before restoring
        $lastVersion = DocumentVersion::where('document_id', $documentId)
            ->max('version_number') ?? 0;

        DocumentVersion::create([
            'id' => Str::uuid()->toString(),
            'document_id' => $documentId,
            'content' => $document->content,
            'version_number' => $lastVersion + 1,
            'change_description' => 'Auto-saved before restoring version ' . $version->version_number,
            'author_id' => auth()->id(),
        ]);

        // Restore the content
        $document->update(['content' => $version->content]);

        return $document->load(['author', 'permissions.user']);
    }
}
