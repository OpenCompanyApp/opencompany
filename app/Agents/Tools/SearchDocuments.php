<?php

namespace App\Agents\Tools;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchDocuments implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
    ) {}

    public function description(): string
    {
        return 'Search workspace documents by keyword. Returns matching document titles and content snippets.';
    }

    public function handle(Request $request): string
    {
        try {
            $query = $request['query'];
            $limit = $request['limit'] ?? 5;

            $lowerQuery = '%' . strtolower($query) . '%';

            $docQuery = Document::where('is_folder', false)
                ->where(function ($q) use ($lowerQuery) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$lowerQuery])
                      ->orWhereRaw('LOWER(content) LIKE ?', [$lowerQuery]);
                });

            // Apply folder scoping if restricted
            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);
            if ($allowedFolderIds !== null) {
                $docQuery->whereIn('parent_id', $allowedFolderIds);
            }

            $documents = $docQuery
                ->orderBy('updated_at', 'desc')
                ->take($limit)
                ->get(['id', 'title', 'content', 'updated_at']);

            if ($documents->isEmpty()) {
                return "No documents found matching '{$query}'.";
            }

            $results = $documents->map(function ($doc) {
                $snippet = Str::limit($doc->content, 300);
                return "**{$doc->title}** (ID: {$doc->id})\n{$snippet}";
            });

            return "Found {$documents->count()} document(s) matching '{$query}':\n\n" . $results->implode("\n\n---\n\n");
        } catch (\Throwable $e) {
            return "Error searching documents: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query to find documents.')
                ->required(),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results to return. Default: 5.'),
        ];
    }
}
