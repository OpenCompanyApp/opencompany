<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Document>
     */
    public function index(): \Illuminate\Database\Eloquent\Collection
    {
        $documents = Document::with(['author', 'parent', 'permissions.user'])
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

            $chunks = $indexer->search(
                query: $query,
                collection: null,
                agentId: null,
                limit: 20,
                minSimilarity: 0.3,
                scopeByAgent: false,
            );
        } catch (\Throwable) {
            $chunks = collect();
        }

        if ($chunks->isEmpty()) {
            return Document::with('author')
                ->where('is_folder', false)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'ilike', '%' . $query . '%')
                      ->orWhere('content', 'ilike', '%' . $query . '%');
                })
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get();
        }

        $docScores = $chunks->groupBy('document_id')
            ->map(fn ($group) => $group->max('similarity'));

        $documents = Document::with('author')
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
        $document = Document::with(['author', 'parent', 'children', 'permissions.user', 'comments.author'])
            ->findOrFail($id);

        return new DocumentResource($document);
    }

    public function store(Request $request): DocumentResource
    {
        $document = Document::create([
            'id' => Str::uuid()->toString(),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'author_id' => $request->input('authorId'),
            'parent_id' => $request->input('parentId'),
            'is_folder' => $request->input('isFolder', false),
            'color' => $request->input('color'),
            'icon' => $request->input('icon'),
            'status' => 'draft',
        ]);

        // Add viewer permissions
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

        // Add editor permissions
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
        $document = Document::findOrFail($id);

        // Save version if requested
        if ($request->input('saveVersion', false) && $document->content) {
            $lastVersion = DocumentVersion::where('document_id', $id)
                ->max('version_number') ?? 0;

            DocumentVersion::create([
                'id' => Str::uuid()->toString(),
                'document_id' => $id,
                'content' => $document->content,
                'version_number' => $lastVersion + 1,
                'change_description' => $request->input('changeDescription'),
                'author_id' => $request->input('authorId', $document->author_id),
            ]);
        }

        $data = $request->only([
            'title',
            'content',
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
                $data['published_at'] = now();
            }
        }

        $document->update($data);

        return new DocumentResource($document->load(['author', 'parent', 'permissions.user']));
    }

    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        $document = Document::findOrFail($id);

        if ($document->is_system) {
            return response()->json(['error' => 'System documents cannot be deleted.'], 403);
        }

        $document->delete();

        return response()->json(['success' => true]);
    }
}
