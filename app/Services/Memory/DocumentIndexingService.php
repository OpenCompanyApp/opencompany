<?php

namespace App\Services\Memory;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentIndexingService
{
    public function __construct(
        private ChunkingService $chunker,
        private EmbeddingService $embedder,
    ) {}

    /**
     * Index a document: chunk its content, embed, and store as DocumentChunks.
     */
    public function index(Document $document, string $collection = 'general', ?string $agentId = null): void
    {
        if (empty(trim($document->content ?? ''))) {
            DocumentChunk::where('document_id', $document->id)->delete();

            return;
        }

        $chunks = $this->chunker->chunk($document->content);

        if (empty($chunks)) {
            DocumentChunk::where('document_id', $document->id)->delete();

            return;
        }

        // Embed first — only delete old chunks after this succeeds
        $embeddings = $this->embedder->embedBatch($chunks);

        DocumentChunk::where('document_id', $document->id)->delete();

        foreach ($chunks as $i => $chunkText) {
            DocumentChunk::create([
                'document_id' => $document->id,
                'content' => $chunkText,
                'content_hash' => hash('sha256', $chunkText),
                'embedding' => $embeddings[$i],
                'collection' => $collection,
                'agent_id' => $agentId,
                'chunk_index' => $i,
                'workspace_id' => $document->workspace_id,
                'metadata' => [
                    'title' => $document->title,
                    'updated_at' => $document->updated_at?->toIso8601String(),
                ],
            ]);
        }

        Log::debug('Document indexed', [
            'document_id' => $document->id,
            'title' => $document->title,
            'collection' => $collection,
            'chunks' => count($chunks),
        ]);

        // Ensure vector index exists for fast similarity search
        $this->ensureVectorIndex(count($embeddings[0] ?? []));
    }

    /**
     * Remove all chunks for a document.
     */
    public function deindex(Document $document): void
    {
        DocumentChunk::where('document_id', $document->id)->delete();
    }

    /**
     * Reset embedding data. Called when the embedding model changes,
     * since vectors from different models are incompatible.
     *
     * @param  string|null  $workspaceId  If provided, only reset data for this workspace. If null, truncate all data.
     */
    public static function resetEmbeddings(?string $workspaceId = null): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if ($workspaceId) {
            // Per-workspace reset: delete only this workspace's data
            DB::table('document_chunks')->where('workspace_id', $workspaceId)->delete();
            DB::table('embedding_cache')->where('workspace_id', $workspaceId)->delete();

            Log::info('Embedding data reset for workspace', ['workspace_id' => $workspaceId]);

            return;
        }

        // Full reset: truncate all data and drop index
        DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
        DB::table('document_chunks')->truncate();
        DB::table('embedding_cache')->truncate();

        // Restore unconstrained vector type (in case it was constrained)
        $dc = DB::selectOne(
            "SELECT atttypmod FROM pg_attribute
             WHERE attrelid = 'document_chunks'::regclass AND attname = 'embedding'"
        );
        if ($dc && $dc->atttypmod > 0) {
            DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector');
        }

        $ec = DB::selectOne(
            "SELECT atttypmod FROM pg_attribute
             WHERE attrelid = 'embedding_cache'::regclass AND attname = 'embedding'"
        );
        if ($ec && $ec->atttypmod > 0) {
            DB::statement('ALTER TABLE embedding_cache ALTER COLUMN embedding TYPE vector');
        }

        self::$hasSearchVector = null;

        Log::info('Embedding data reset — tables truncated, HNSW index dropped');
    }

    /**
     * Hybrid search across document chunks (vector + full-text).
     *
     * Combines pgvector cosine similarity with PostgreSQL full-text search
     * using Reciprocal Rank Fusion (RRF). Gracefully falls back to a single
     * source when the other is unavailable.
     *
     * @return Collection<int, DocumentChunk>  Ordered by relevance (highest first)
     */
    public function search(
        string $query,
        ?string $collection = 'general',
        ?string $agentId = null,
        int $limit = 10,
        float $minSimilarity = 0.5,
        bool $scopeByAgent = true,
    ): Collection {
        $vectorResults = $this->vectorSearch($query, $collection, $agentId, $limit, $minSimilarity, $scopeByAgent);
        $ftsResults = $this->ftsSearch($query, $collection, $agentId, $limit, $scopeByAgent);

        if ($ftsResults->isEmpty()) {
            return $vectorResults;
        }

        if ($vectorResults->isEmpty()) {
            return $ftsResults;
        }

        return $this->mergeWithRRF($vectorResults, $ftsResults, $limit);
    }

    /**
     * Vector similarity search using pgvector cosine distance.
     *
     * @return Collection<int, DocumentChunk>
     */
    private function vectorSearch(
        string $query,
        ?string $collection,
        ?string $agentId,
        int $limit,
        float $minSimilarity,
        bool $scopeByAgent = true,
    ): Collection {
        try {
            $queryEmbedding = $this->embedder->embed($query);
        } catch (\Throwable $e) {
            Log::debug('Vector search skipped: embedding failed', ['error' => $e->getMessage()]);

            return collect();
        }

        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $builder = DocumentChunk::query()
            ->selectRaw('*, 1 - (embedding <=> ?) as similarity', [$vectorString])
            ->whereRaw('1 - (embedding <=> ?) >= ?', [$vectorString, $minSimilarity])
            ->orderByDesc('similarity')
            ->limit($limit);

        // Add workspace scope if workspace context is available
        $workspace = app('currentWorkspace');
        if ($workspace) {
            $builder->where('workspace_id', $workspace->id);
        }

        if ($collection !== null) {
            $builder->where('collection', $collection);
        }

        if ($scopeByAgent) {
            if ($agentId !== null) {
                $builder->where('agent_id', $agentId);
            } else {
                $builder->whereNull('agent_id');
            }
        }

        return $builder->get();
    }

    /**
     * Full-text search using PostgreSQL tsvector/tsquery.
     *
     * @return Collection<int, DocumentChunk>
     */
    private function ftsSearch(
        string $query,
        ?string $collection,
        ?string $agentId,
        int $limit,
        bool $scopeByAgent = true,
    ): Collection {
        if (DB::getDriverName() !== 'pgsql' || ! $this->hasSearchVectorColumn()) {
            return collect();
        }

        $tsQuery = $this->buildTsQuery($query);
        if ($tsQuery === '') {
            return collect();
        }

        try {
            $builder = DocumentChunk::query()
                ->whereRaw("search_vector @@ to_tsquery('english', ?)", [$tsQuery])
                ->selectRaw("*, ts_rank(search_vector, to_tsquery('english', ?)) as similarity", [$tsQuery])
                ->orderByDesc('similarity')
                ->limit($limit);

            // Add workspace scope if workspace context is available
            $workspace = app('currentWorkspace');
            if ($workspace) {
                $builder->where('workspace_id', $workspace->id);
            }

            if ($collection !== null) {
                $builder->where('collection', $collection);
            }

            if ($scopeByAgent) {
                if ($agentId !== null) {
                    $builder->where('agent_id', $agentId);
                } else {
                    $builder->whereNull('agent_id');
                }
            }

            return $builder->get();
        } catch (\Throwable $e) {
            Log::debug('FTS search failed', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /**
     * Merge vector and FTS results using weighted Reciprocal Rank Fusion.
     *
     * Scores are normalized to 0–1 so callers can display as percentages.
     *
     * @param  Collection<int, DocumentChunk>  $vectorResults
     * @param  Collection<int, DocumentChunk>  $ftsResults
     * @return Collection<int, DocumentChunk>
     */
    private function mergeWithRRF(Collection $vectorResults, Collection $ftsResults, int $limit): Collection
    {
        $k = 60;
        $semanticWeight = (float) config('memory.search.hybrid_weights.semantic', 0.7);
        $keywordWeight = (float) config('memory.search.hybrid_weights.keyword', 0.3);

        $vectorRanks = $vectorResults->values()->mapWithKeys(
            fn (DocumentChunk $chunk, int $i) => [$chunk->id => $i]
        );
        $ftsRanks = $ftsResults->values()->mapWithKeys(
            fn (DocumentChunk $chunk, int $i) => [$chunk->id => $i]
        );

        /** @var Collection<string, DocumentChunk> $allChunks */
        $allChunks = collect();
        foreach ($vectorResults as $chunk) {
            $allChunks[$chunk->id] = $chunk;
        }
        foreach ($ftsResults as $chunk) {
            if (! $allChunks->has($chunk->id)) {
                $allChunks[$chunk->id] = $chunk;
            }
        }

        $penaltyRank = $limit + 1;

        $scored = $allChunks->map(function (DocumentChunk $chunk) use (
            $vectorRanks, $ftsRanks, $k, $semanticWeight, $keywordWeight, $penaltyRank,
        ) {
            $vRank = $vectorRanks->get($chunk->id, $penaltyRank);
            $fRank = $ftsRanks->get($chunk->id, $penaltyRank);

            $chunk->similarity = ($semanticWeight / ($k + $vRank + 1)) + ($keywordWeight / ($k + $fRank + 1));

            return $chunk;
        });

        // Normalize to 0–1 so callers can display as percentages
        $maxScore = $scored->max('similarity');
        if ($maxScore > 0) {
            $scored = $scored->map(function (DocumentChunk $chunk) use ($maxScore) {
                $chunk->similarity = $chunk->similarity / $maxScore;

                return $chunk;
            });
        }

        return $scored->sortByDesc('similarity')->take($limit)->values();
    }

    /**
     * Convert a natural language query to a PostgreSQL tsquery string.
     */
    private function buildTsQuery(string $query): string
    {
        $words = array_filter(
            explode(' ', trim($query)),
            fn (string $w) => mb_strlen($w) > 1
        );

        if (empty($words)) {
            return '';
        }

        $sanitized = array_map(
            fn (string $w) => preg_replace('/[^a-zA-Z0-9]/', '', $w),
            $words
        );

        $filtered = array_filter($sanitized, fn (string $w) => $w !== '');

        return implode(' & ', $filtered);
    }

    private static ?bool $hasSearchVector = null;

    /**
     * Check if the search_vector column exists (migration may not have run yet).
     */
    private function hasSearchVectorColumn(): bool
    {
        if (self::$hasSearchVector !== null) {
            return self::$hasSearchVector;
        }

        self::$hasSearchVector = DB::selectOne(
            "SELECT 1 FROM information_schema.columns
             WHERE table_name = 'document_chunks' AND column_name = 'search_vector'"
        ) !== null;

        return self::$hasSearchVector;
    }

    /**
     * Ensure a vector index exists on document_chunks.embedding.
     *
     * Uses HNSW for ≤2000 dimensions (pgvector index limit). For larger
     * embeddings, no index is created — search falls back to sequential scan.
     * Requires a dimension-constrained vector column, so this method ALTERs
     * the unconstrained column first. One-time operation per model.
     */
    private function ensureVectorIndex(int $dimensions): void
    {
        if ($dimensions <= 0 || DB::getDriverName() !== 'pgsql') {
            return;
        }

        $exists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'document_chunks' AND indexname = 'document_chunks_embedding_idx'"
        );

        if ($exists) {
            return;
        }

        // pgvector HNSW and IVFFlat both support max 2000 dimensions.
        // For larger embeddings, skip index creation — brute-force search
        // still works, just without index acceleration.
        if ($dimensions > 2000) {
            Log::info('Vector index skipped: embedding has {dims} dimensions (pgvector indexes support max 2000). Search will use sequential scan.', [
                'dims' => $dimensions,
            ]);

            return;
        }

        try {
            DB::statement("ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector({$dimensions})");
            DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
            Log::info('HNSW index created on document_chunks', ['dimensions' => $dimensions]);
        } catch (\Throwable $e) {
            Log::warning('Could not create vector index', ['error' => $e->getMessage()]);
        }
    }
}
