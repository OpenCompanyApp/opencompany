<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetDocument implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Get full details of a document or folder by ID. Returns content (or child listing for folders), metadata, and comment count.';
    }

    public function handle(Request $request): string
    {
        try {
            $documentId = $request['documentId'] ?? null;
            if (!$documentId) {
                return "Error: 'documentId' is required.";
            }

            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);

            $doc = Document::forWorkspace()
                ->with(['author:id,name', 'parent:id,title'])
                ->withCount('comments')
                ->find($documentId);

            if (!$doc) {
                return "Error: Document '{$documentId}' not found.";
            }

            // Permission check: if it's a doc in a folder, check folder access
            // If it's a folder itself, check if it's in the allowed list
            if ($allowedFolderIds !== null) {
                if ($doc->is_folder && !in_array($doc->id, $allowedFolderIds)) {
                    return 'Permission denied: you do not have access to this folder.';
                }
                if (!$doc->is_folder && $doc->parent_id && !in_array($doc->parent_id, $allowedFolderIds)) {
                    return 'Permission denied: you do not have access to this document.';
                }
            }

            $author = $doc->author ? $doc->author->name : 'Unknown';
            $parent = $doc->parent ? "{$doc->parent->title} (ID: {$doc->parent_id})" : 'Root';

            $lines = [
                "Title: {$doc->title}",
                "Type: " . ($doc->is_folder ? 'Folder' : 'Document'),
                "ID: {$doc->id}",
                "Author: {$author}",
                "Parent: {$parent}",
                "Created: " . $doc->created_at->format('Y-m-d H:i'),
                "Updated: " . $doc->updated_at->format('Y-m-d H:i'),
                "Comments: {$doc->comments_count}",
            ];

            if ($doc->is_system) {
                $lines[] = "System: yes (cannot be deleted)";
            }

            if ($doc->is_folder) {
                // Show folder contents summary
                $children = Document::forWorkspace()
                    ->where('parent_id', $doc->id)
                    ->orderBy('is_folder', 'desc')
                    ->orderBy('title')
                    ->take(50)
                    ->get(['id', 'title', 'is_folder']);

                $folderCount = $children->where('is_folder', true)->count();
                $docCount = $children->where('is_folder', false)->count();
                $lines[] = "Contents: {$docCount} documents, {$folderCount} subfolders";

                foreach ($children as $child) {
                    $prefix = $child->is_folder ? '[Folder] ' : '';
                    $lines[] = "  - {$prefix}{$child->title} (ID: {$child->id})";
                }
            } else {
                // Show content snippet
                $lines[] = "Content:\n" . Str::limit($doc->content, 1000);
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return "Error getting document: {$e->getMessage()}";
        }
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'documentId' => $schema
                ->string()
                ->description('The UUID of the document or folder to retrieve.')
                ->required(),
        ];
    }
}
