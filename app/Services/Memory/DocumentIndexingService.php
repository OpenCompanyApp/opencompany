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
        // Delete existing chunks for this document
        DocumentChunk::where('document_id', $document->id)->delete();

        if (empty(trim($document->content ?? ''))) {
            return;
        }

        $chunks = $this->chunker->chunk($document->content);

        if (empty($chunks)) {
            return;
        }

        $embeddings = $this->embedder->embedBatch($chunks);

        foreach ($chunks as $i => $chunkText) {
            DocumentChunk::create([
                'document_id' => $document->id,
                'content' => $chunkText,
                'content_hash' => hash('sha256', $chunkText),
                'embedding' => $embeddings[$i],
                'collection' => $collection,
                'agent_id' => $agentId,
                'chunk_index' => $i,
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

        // Ensure HNSW index exists for fast similarity search
        $this->ensureHnswIndex(count($embeddings[0] ?? []));
    }

    /**
     * Remove all chunks for a document.
     */
    public function deindex(Document $document): void
    {
        DocumentChunk::where('document_id', $document->id)->delete();
    }

    /**
     * Reset all embedding data. Called when the embedding model changes,
     * since vectors from different models are incompatible.
     */
    public static function resetEmbeddings(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

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

        Log::info('Embedding data reset — tables truncated, HNSW index dropped');
    }

    /**
     * Semantic search across document chunks.
     *
     * @return Collection<int, DocumentChunk>  Ordered by similarity (highest first)
     */
    public function search(
        string $query,
        string $collection = 'general',
        ?string $agentId = null,
        int $limit = 10,
        float $minSimilarity = 0.5,
    ): Collection {
        $queryEmbedding = $this->embedder->embed($query);
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $builder = DocumentChunk::query()
            ->where('collection', $collection)
            ->selectRaw('*, 1 - (embedding <=> ?) as similarity', [$vectorString])
            ->having('similarity', '>=', $minSimilarity)
            ->orderByDesc('similarity')
            ->limit($limit);

        if ($agentId !== null) {
            $builder->where('agent_id', $agentId);
        } else {
            $builder->whereNull('agent_id');
        }

        return $builder->get();
    }

    /**
     * Ensure the HNSW index exists on document_chunks.embedding.
     *
     * HNSW requires a dimension-constrained vector column, so this method
     * ALTERs the unconstrained column to add the dimension and then creates
     * the index. This is a one-time operation per embedding model.
     */
    private function ensureHnswIndex(int $dimensions): void
    {
        if ($dimensions <= 0 || DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Check if index already exists
        $exists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'document_chunks' AND indexname = 'document_chunks_embedding_idx'"
        );

        if ($exists) {
            return;
        }

        try {
            // Add dimension constraint (required for HNSW)
            DB::statement("ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector({$dimensions})");
            DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');

            Log::info('HNSW index created on document_chunks', ['dimensions' => $dimensions]);
        } catch (\Throwable $e) {
            Log::warning('Could not create HNSW index', ['error' => $e->getMessage()]);
        }
    }
}
