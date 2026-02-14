<?php

namespace App\Agents\Tools\Docs;

use App\Models\Document;
use App\Models\User;
use App\Services\AgentPermissionService;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchDocuments implements Tool
{
    public function __construct(
        private User $agent,
        private AgentPermissionService $permissionService,
        private ?DocumentIndexingService $indexer = null,
    ) {}

    public function description(): string
    {
        return 'Search workspace documents by keyword or semantic similarity. Returns matching document titles and content snippets.';
    }

    public function handle(Request $request): string
    {
        try {
            $query = $request['query'];
            $limit = $request['limit'] ?? 5;
            $mode = $request['mode'] ?? 'auto';

            $semanticResults = null;
            $semanticCount = 0;

            if ($mode === 'semantic' || $mode === 'auto') {
                [$semanticResults, $semanticCount] = $this->semanticSearch($query, $limit);

                if ($semanticResults !== null && ($mode === 'semantic' || $semanticCount >= 2)) {
                    return $semanticResults ?: "No documents found matching '{$query}'.";
                }
            }

            // Keyword search (or auto fallback)
            $keywordResult = $this->keywordSearch($query, $limit);

            // In auto mode, merge semantic + keyword if we got some semantic results
            if ($mode === 'auto' && $semanticResults !== null && $semanticResults !== '') {
                return $semanticResults . "\n\n---\n\n**Keyword matches:**\n\n" . $keywordResult;
            }

            return $keywordResult;
        } catch (\Throwable $e) {
            return "Error searching documents: {$e->getMessage()}";
        }
    }

    /**
     * @return array{0: ?string, 1: int}  [formatted results or null, count]
     */
    private function semanticSearch(string $query, int $limit): array
    {
        if (!$this->indexer) {
            return [null, 0];
        }

        try {
            $chunks = $this->indexer->search(
                query: $query,
                collection: 'general',
                agentId: null,
                limit: $limit,
                minSimilarity: config('memory.search.min_similarity', 0.5),
            );

            if ($chunks->isEmpty()) {
                return ['', 0];
            }

            // Filter by folder permissions (same as keyword search)
            $allowedFolderIds = $this->permissionService->getAllowedFolderIds($this->agent);
            if ($allowedFolderIds !== null) {
                $docIds = $chunks->pluck('document_id')->unique()->filter();
                $allowedDocIds = Document::whereIn('id', $docIds)
                    ->whereIn('parent_id', $allowedFolderIds)
                    ->pluck('id');
                $chunks = $chunks->filter(fn ($c) => $allowedDocIds->contains($c->document_id));

                if ($chunks->isEmpty()) {
                    return ['', 0];
                }
            }

            $results = $chunks->map(function ($chunk) {
                $similarity = isset($chunk->similarity) ? round($chunk->similarity * 100) . '% match' : '';
                $meta = is_array($chunk->metadata) ? $chunk->metadata : [];
                $title = $meta['title'] ?? 'Untitled';
                $snippet = Str::limit($chunk->content, 300);

                return "**{$title}** ({$similarity})\n{$snippet}";
            });

            return [
                "Found {$chunks->count()} relevant document(s):\n\n" . $results->implode("\n\n---\n\n"),
                $chunks->count(),
            ];
        } catch (\Throwable) {
            return [null, 0];
        }
    }

    private function keywordSearch(string $query, int $limit): string
    {
        $lowerQuery = '%' . strtolower($query) . '%';

        $docQuery = Document::where('is_folder', false)
            ->where(function ($q) use ($lowerQuery) {
                $q->whereRaw('LOWER(title) LIKE ?', [$lowerQuery])
                  ->orWhereRaw('LOWER(content) LIKE ?', [$lowerQuery]);
            });

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
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query to find documents.')
                ->required(),
            'mode' => $schema
                ->string()
                ->description('Search mode: "keyword" (exact text match), "semantic" (vector similarity), or "auto" (semantic first, keyword fallback). Default: auto.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results to return. Default: 5.'),
        ];
    }
}
