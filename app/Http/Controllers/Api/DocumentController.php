<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::with(['author', 'parent', 'permissions.user'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return $documents;
    }

    public function show(string $id)
    {
        $document = Document::with(['author', 'parent', 'children', 'permissions.user', 'comments.author'])
            ->findOrFail($id);

        return new DocumentResource($document);
    }

    public function store(Request $request)
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

    public function update(Request $request, string $id)
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

    public function destroy(string $id)
    {
        $document = Document::findOrFail($id);

        if ($document->is_system) {
            return response()->json(['error' => 'System documents cannot be deleted.'], 403);
        }

        $document->delete();

        return response()->json(['success' => true]);
    }
}
