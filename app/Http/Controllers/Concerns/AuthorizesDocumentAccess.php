<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Authorization\Application\AuthorizationService;
use App\Models\Document;
use Illuminate\Http\Request;

/**
 * Shared lookup for document sub-resources (comments, versions,
 * attachments): find the document in the current workspace, then ask
 * AuthorizationService for the action (GIN-1199). Unreadable documents are a
 * 404, like missing ones; readable but not writable ones a 403.
 */
trait AuthorizesDocumentAccess
{
    protected function authorizedDocument(Request $request, string $documentId, string $action): Document
    {
        $document = Document::forWorkspace()->findOrFail($documentId);
        app(AuthorizationService::class)->authorize($request->user(), $action, $document);

        return $document;
    }
}
