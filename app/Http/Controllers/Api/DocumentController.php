<?php

namespace App\Http\Controllers\Api;

use App\Domain\Authorization\Application\AuthorizationFacts;
use App\Domain\Authorization\Application\AuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\WorkspaceMember;
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
 *
 * Authorization (GIN-1199; ROADMAP P0 item 3): stored sharing rows
 * (document_permissions) are enforced, never silently ignored, through
 * AuthorizationService — `document.read` for show, list and every search
 * result, `document.create` on the parent folder (or the root),
 * `document.write` for edits and moves, `document.delete` for trash. A
 * document's rows restrict it and its whole subtree to its author and the
 * listed users; editors may write, viewers only read. There is no admin
 * override. Unreadable documents are a 404, like missing ones. Pillar 5
 * replaces these rows with Space-level access later.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationFacts $facts,
    ) {}

    /**
     * @return Collection<int, Document>
     */
    public function index(Request $request): Collection
    {
        $documents = Document::forWorkspace()->with(['author', 'parent', 'permissions.user'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->readable($request->user(), $documents);
    }

    /**
     * @return JsonResponse|mixed
     */
    public function search(Request $request)
    {
        $actor = $request->user();
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
            // Over-fetch, then drop what the actor may not read: titles and
            // snippets of unshared documents never leave the server.
            $matches = Document::forWorkspace()->with('author')
                ->where('is_folder', false)
                ->where(function ($q) use ($query) {
                    $q->whereLike('title', '%'.$query.'%')
                        ->orWhereLike('content', '%'.$query.'%');
                })
                ->orderBy('updated_at', 'desc')
                ->limit(50)
                ->get();

            return $this->readable($actor, $matches)->take(10)->values();
        }

        $docScores = $chunks->groupBy('document_id')
            ->map(fn ($group) => $group->max('similarity'));

        $documents = $this->readable($actor, Document::forWorkspace()->with('author')
            ->whereIn('id', $docScores->keys())
            ->where('is_folder', false)
            ->get())
            ->sortByDesc(fn (Document $doc) => $docScores->get($doc->id, 0))
            ->values();

        return $documents->map(fn (Document $doc) => array_merge(
            $doc->toArray(),
            ['relevance' => round(($docScores->get($doc->id, 0)) * 100)]
        ));
    }

    public function show(Request $request, string $id): DocumentResource
    {
        $document = Document::forWorkspace()->with(['author', 'parent', 'children', 'permissions.user', 'comments.author'])
            ->findOrFail($id);
        $this->authorization->authorize($request->user(), 'document.read', $document);

        // Children with their own sharing rows may exclude the reader.
        $document->setRelation('children', $this->readable($request->user(), $document->children));

        return new DocumentResource($document);
    }

    public function store(Request $request): DocumentResource|JsonResponse
    {
        // Invariant: the parent must be a folder of this workspace the actor
        // may write in (or the root); checked before anything is created.
        $parent = $request->filled('parentId') ? Document::forWorkspace()->findOrFail($request->input('parentId')) : null;
        $this->authorization->authorize($request->user(), 'document.create', $parent ?? workspace());

        $shareIds = array_merge((array) $request->input('viewerIds', []), (array) $request->input('editorIds', []));
        if (($outside = $this->outsideWorkspace($shareIds)) !== []) {
            return response()->json(['message' => 'Documents can only be shared with people and agents of this workspace.', 'error' => 'target_not_in_workspace'], 422);
        }

        $document = Document::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => workspace()->id,
            'title' => $request->input('title'),
            // documents.content is NOT NULL and empty bodies arrive as null
            // (ConvertEmptyStringsToNull), so store an empty document.
            'content' => (string) $request->input('content', ''),
            'author_id' => auth()->id(),
            'parent_id' => $parent?->id,
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

    public function update(Request $request, string $id): DocumentResource|JsonResponse
    {
        $document = Document::forWorkspace()->findOrFail($id);
        $this->authorization->authorize($request->user(), 'document.write', $document);

        // A move needs write access to the destination folder too, and may
        // never put a document inside itself or its own subtree.
        if ($request->has('parentId') && $request->input('parentId') !== $document->parent_id) {
            $target = $request->filled('parentId') ? Document::forWorkspace()->findOrFail($request->input('parentId')) : null;
            if ($target !== null && in_array((string) $document->id, array_column($this->facts->document($target)['chain'], 'id'), true)) {
                return response()->json(['message' => 'A document cannot be moved inside itself.', 'error' => 'document_move_cycle'], 422);
            }
            $this->authorization->authorize($request->user(), 'document.create', $target ?? workspace());
        }

        // Invariant: optimistic concurrency for content. Agents edit documents
        // through the VFS, which checks the same version token; without this
        // check an open editor's autosave would silently overwrite them. A
        // content write must name the version it was based on (body
        // `baseVersion` or an `If-Match` header). Metadata-only updates (title,
        // icon, colour, parent, publish) are not guarded.
        if ($request->has('content')) {
            $baseVersion = (string) ($request->input('baseVersion') ?? trim((string) $request->header('If-Match'), '"'));
            if ($baseVersion === '') {
                return response()->json([
                    'error' => 'version_required',
                    'message' => 'Content updates must include the document version they are based on (baseVersion or If-Match).',
                    'currentVersion' => $document->contentVersion(),
                ], 428);
            }
            if (! hash_equals($document->contentVersion(), $baseVersion)) {
                return response()->json([
                    'error' => 'version_conflict',
                    'message' => 'This document changed since you opened it. Reload to see the latest version before saving.',
                    'document' => new DocumentResource($document->load(['author'])),
                ], 409);
            }
        }

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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $document = Document::forWorkspace()->findOrFail($id);

        // Agent identity/memory documents can be removed only through
        // AgentDocumentService, which intentionally clears system guards; the
        // engine denies them here (`system_document`).
        $this->authorization->authorize($request->user(), 'document.delete', $document);

        // Trash, not purge; a folder takes its live subtree with it as one
        // restorable batch (TrashesInBatches). Never a bare UPDATE here: the
        // old physical delete cascaded through parent_id, a soft delete only
        // does so through the model.
        $document->trashAsBatch(auth()->id() !== null ? (string) auth()->id() : null);

        return response()->json(['success' => true]);
    }

    /**
     * The documents the actor may read, in their original order. One facts
     * load for the whole list (two queries), then the engine per document.
     *
     * @param  \Illuminate\Support\Collection<int, Document>|Collection<int, Document>  $documents
     * @return Collection<int, Document>
     */
    private function readable(User $actor, $documents): Collection
    {
        $facts = $this->facts->documents(workspace(), $documents);
        $decisions = $this->authorization->decideMany($actor, 'document.read', $facts, workspace());

        return new Collection($documents->filter(fn (Document $document): bool => ($decisions[(string) $document->id] ?? null)?->allowed() === true)->values()->all());
    }

    /**
     * Ids in $userIds that are neither members nor agents of this workspace.
     *
     * @param  array<mixed>  $userIds
     * @return list<string>
     */
    private function outsideWorkspace(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('strval', $userIds)));
        if ($userIds === []) {
            return [];
        }
        $inside = WorkspaceMember::query()->where('workspace_id', workspace()->id)->whereIn('user_id', $userIds)->pluck('user_id')
            ->merge(User::query()->where('type', 'agent')->where('workspace_id', workspace()->id)->whereIn('id', $userIds)->pluck('id'))
            ->map(fn ($id) => (string) $id)->all();

        return array_values(array_diff($userIds, $inside));
    }
}
