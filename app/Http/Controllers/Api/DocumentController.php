<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD and search API for workspace documents.
 *
 * Documents include ordinary user content and system-owned agent identity files.
 * This controller scopes all lookups through the current workspace and delegates
 * semantic search to the memory index when available.
 */
class DocumentController extends Controller
{
    /**
     * @return Collection<int, Document>
     */
    public function index(): Collection
    {
        $documents = Document::forWorkspace()->with(['author', 'parent', 'permissions.user'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return $documents;
    }

    /**
     * @return JsonResponse|mixed
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        try {
            $indexer = app(DocumentIndexingService::class);

            // Search all workspace document chunks, including identity/memory
            // collections, but do not scope by one agent for the general docs UI.
            $chunks = $indexer->search(
                query: $query,
                collection: null,
                agentId: null,
                limit: 20,
                minSimilarity: 0.3,
                scopeByAgent: false,
            );
        } catch (\Throwable) {
            // If embeddings or pgvector are unavailable, fall back to simple
            // database search so the docs UI remains functional.
            $chunks = collect();
        }

        if ($chunks->isEmpty()) {
            // Fallback query stays workspace-scoped and excludes folders because
            // only document content should appear as text search results.
            return Document::forWorkspace()->with('author')
                ->where('is_folder', false)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'ilike', '%'.$query.'%')
                        ->orWhere('content', 'ilike', '%'.$query.'%');
                })
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get();
        }

        $docScores = $chunks->groupBy('document_id')
            ->map(fn ($group) => $group->max('similarity'));

        $documents = Document::forWorkspace()->with('author')
            ->whereIn('id', $docScores->keys())
            ->where('is_folder', false)
            ->get()
            ->sortByDesc(fn ($doc) => $docScores->get($doc->id, 0))
            ->values();

        return $documents->map(fn ($doc) => array_merge(
            $doc->toArray(),
            ['relevance' => round(($docScores->get($doc->id, 0)) * 100)]
        ));
    }

    public function show(string $id): DocumentResource
    {
        $document = Document::forWorkspace()->with(['author', 'parent', 'children', 'permissions.user', 'comments.author'])
            ->findOrFail($id);

        return new DocumentResource($document);
    }

    public function store(Request $request): DocumentResource
    {
        // New documents inherit the current workspace from ResolveWorkspace.
        // Parent validation is handled by workspace-scoped UI/routes for now.
        $document = Document::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'author_id' => auth()->id(),
            'parent_id' => $request->input('parentId'),
            'is_folder' => $request->input('isFolder', false),
            'color' => $request->input('color'),
            'icon' => $request->input('icon'),
            'status' => 'draft',
        ]);

        // Permission rows are optional sharing hints; the workspace boundary is
        // still enforced separately by the query scopes around documents.
        if ($request->input('viewerIds')) {
            foreach ($request->input('viewerIds') as $userId) {
                DocumentPermission::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $document->id,
                    'user_id' => $userId,
                    'role' => 'viewer',
                ]);
            }
        }

        if ($request->input('editorIds')) {
            foreach ($request->input('editorIds') as $userId) {
                DocumentPermission::create([
                    'id' => Str::uuid()->toString(),
                    'document_id' => $document->id,
                    'user_id' => $userId,
                    'role' => 'editor',
                ]);
            }
        }

        return new DocumentResource($document->load(['author', 'permissions.user']));
    }

    public function update(Request $request, string $id): DocumentResource
    {
        $document = Document::forWorkspace()->findOrFail($id);

        // Version snapshots are explicit so autosaves do not create noisy
        // history. Capture the previous content before applying updates.
        if ($request->input('saveVersion', false) && $document->content) {
            $lastVersion = DocumentVersion::where('document_id', $id)
                ->max('version_number') ?? 0;

            DocumentVersion::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $id,
                'content' => $document->content,
                'version_number' => $lastVersion + 1,
                'change_description' => $request->input('changeDescription'),
                'author_id' => auth()->id(),
            ]);
        }

        $data = $request->only([
            'title',
            'content',
            'content_format',
            'status',
            'color',
            'icon',
        ]);

        if ($request->has('parentId')) {
            $data['parent_id'] = $request->input('parentId');
        }
        if ($request->has('isPublished')) {
            $data['is_published'] = $request->input('isPublished');
            if ($request->input('isPublished')) {
                // First publish timestamp is used by readers and audit views;
                // keep it close to the flag transition.
                $data['published_at'] = now();
            }
        }

        $document->update($data);

        return new DocumentResource($document->load(['author', 'parent', 'permissions.user']));
    }

    public function destroy(string $id): JsonResponse
    {
        $document = Document::forWorkspace()->findOrFail($id);

        if ($document->is_system) {
            // Agent identity/memory documents can be removed only through
            // AgentDocumentService, which intentionally clears system guards.
            return response()->json(['error' => 'System documents cannot be deleted.'], 403);
        }

        $document->delete();

        return response()->json(['success' => true]);
    }
}
