<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceFile;
use App\Services\FileSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Workspace file-manager API.
 *
 * File metadata is always workspace-scoped before storage bytes are read or
 * written. The FileSystemService owns disk resolution and parent validation; the
 * controller owns request validation and API response shape.
 */
class FileController extends Controller
{
    public function __construct(
        private FileSystemService $fileSystemService,
    ) {}

    /**
     * List files at root or within a parent folder.
     */
    public function index(Request $request): JsonResponse
    {
        $parentId = $request->query('parent_id');
        $search = $request->query('search');
        $workspaceId = workspace()->id;

        $diskId = $request->query('disk_id');

        // Listing uses metadata only; physical storage is touched only for
        // upload/download/copy/delete operations.
        $items = $this->fileSystemService->listDirectory($workspaceId, $parentId, $search, $diskId);

        return response()->json([
            'data' => $items->map(fn (WorkspaceFile $f) => $this->formatFile($f))->values(),
            'parentId' => $parentId,
        ]);
    }

    /**
     * Get the folder tree structure.
     */
    public function tree(): JsonResponse
    {
        $tree = $this->fileSystemService->getFolderTree(workspace()->id);

        return response()->json($tree);
    }

    /**
     * Search files by name.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $mimeType = $request->query('mime_type');

        if (strlen($query) < 2) {
            // Avoid broad workspace scans for one-character searches.
            return response()->json(['data' => []]);
        }

        $results = $this->fileSystemService->searchFiles(workspace()->id, $query, $mimeType);

        return response()->json([
            'data' => $results->map(fn (WorkspaceFile $f) => $this->formatFile($f))->values(),
        ]);
    }

    /**
     * Upload file(s).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
            'parent_id' => 'nullable|string',
            'disk_id' => 'nullable|string',
        ]);

        $parentId = $request->input('parent_id');
        $workspaceId = workspace()->id;

        $file = $this->fileSystemService->uploadFile(
            $workspaceId,
            $parentId,
            $request->file('file'),
            auth()->id(),
            $request->input('disk_id'),
        );

        return response()->json($this->formatFile($file->load('owner')), 201);
    }

    /**
     * Create a folder.
     */
    public function createFolder(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|string',
            'disk_id' => 'nullable|string',
        ]);

        $folder = $this->fileSystemService->createFolder(
            workspace()->id,
            $request->input('parent_id'),
            $request->input('name'),
            auth()->id(),
            $request->input('disk_id'),
        );

        return response()->json($this->formatFile($folder->load('owner')), 201);
    }

    /**
     * Get file/folder details.
     */
    public function show(string $id): JsonResponse
    {
        $file = WorkspaceFile::forWorkspace()->with('owner')->findOrFail($id);

        return response()->json($this->formatFile($file));
    }

    /**
     * List folder contents.
     */
    public function children(string $id): JsonResponse
    {
        $folder = WorkspaceFile::forWorkspace()->where('is_folder', true)->findOrFail($id);

        $items = $this->fileSystemService->listDirectory(workspace()->id, $folder->id);

        return response()->json([
            'data' => $items->map(fn (WorkspaceFile $f) => $this->formatFile($f))->values(),
            'parentId' => $folder->id,
            'parentName' => $folder->name,
            'parentPath' => $folder->getVirtualPath(),
        ]);
    }

    /**
     * Download a file.
     */
    public function download(string $id): StreamedResponse|JsonResponse
    {
        $file = WorkspaceFile::forWorkspace()->findOrFail($id);

        if ($file->is_folder) {
            return response()->json(['error' => 'Cannot download a folder.'], 400);
        }

        $contents = $this->fileSystemService->readFileContents($file);
        if ($contents === null) {
            // Metadata exists but storage bytes do not. Treat this as a storage
            // miss rather than leaking disk details to the client.
            return response()->json(['error' => 'File not found on storage.'], 404);
        }

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $file->name, [
            'Content-Type' => $file->mime_type ?? 'application/octet-stream',
            'Content-Length' => $file->size,
        ]);
    }

    /**
     * Update (rename/move) a file or folder.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $file = WorkspaceFile::forWorkspace()->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'parent_id' => 'sometimes|nullable|string',
        ]);

        if ($request->has('description')) {
            $file->update(['description' => $request->input('description')]);
        }

        // Move/rename is metadata-only. The physical storage path does not need
        // to change because WorkspaceFile represents the virtual tree.
        $newParentId = $request->has('parent_id') ? $request->input('parent_id') : $file->parent_id;
        $newName = $request->input('name');

        $updated = $this->fileSystemService->moveFile($file, $newParentId, $newName);

        return response()->json($this->formatFile($updated->load('owner')));
    }

    /**
     * Delete a file or folder.
     */
    public function destroy(string $id): JsonResponse
    {
        $file = WorkspaceFile::forWorkspace()->findOrFail($id);

        $this->fileSystemService->deleteFile($file);

        return response()->json(['success' => true]);
    }

    /**
     * Copy a file.
     */
    public function copy(Request $request, string $id): JsonResponse
    {
        $file = WorkspaceFile::forWorkspace()->findOrFail($id);

        $request->validate([
            'parent_id' => 'required|string',
            'name' => 'nullable|string|max:255',
        ]);

        $copy = $this->fileSystemService->copyFile(
            $file,
            $request->input('parent_id'),
            $request->input('name'),
        );

        return response()->json($this->formatFile($copy->load('owner')), 201);
    }

    /**
     * Format a WorkspaceFile for API response.
     */
    private function formatFile(WorkspaceFile $file): array
    {
        $data = [
            'id' => $file->id,
            'name' => $file->name,
            'description' => $file->description,
            'isFolder' => $file->is_folder,
            'parentId' => $file->parent_id,
            'virtualPath' => $file->getVirtualPath(),
            'createdAt' => $file->created_at->toIso8601String(),
            'updatedAt' => $file->updated_at->toIso8601String(),
            'diskId' => $file->workspace_disk_id,
            'diskName' => $file->workspace_disk_id ? ($file->relationLoaded('workspaceDisk') ? $file->workspaceDisk?->name : null) : null,
        ];

        if ($file->relationLoaded('owner') && $file->owner) {
            $data['owner'] = [
                'id' => $file->owner->id,
                'name' => $file->owner->name,
                'avatar' => $file->owner->avatar,
                'type' => $file->owner->type,
            ];
        }

        if (! $file->is_folder) {
            $data['mimeType'] = $file->mime_type;
            $data['size'] = $file->size;
            // Downloads go through the API so app authorization can run before
            // bytes are streamed from private/local disks.
            $data['downloadUrl'] = "/api/files/{$file->id}/download";
        } else {
            $data['childCount'] = $file->children()->count();
        }

        return $data;
    }
}
