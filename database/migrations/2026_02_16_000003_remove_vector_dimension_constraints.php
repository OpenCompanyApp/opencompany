<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove hardcoded dimension constraints from vector columns.
 *
 * pgvector's unconstrained `vector` type accepts any dimension — the
 * actual dimension is enforced by the HNSW index which is created lazily
 * by DocumentIndexingService after the first embeddings are stored.
 *
 * This lets users switch embedding models (which may have different
 * output dimensions) without a schema change; the embedding tables
 * just need to be truncated first since old embeddings are incompatible
 * with a new model's vector space.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Remove dimension constraint from document_chunks.embedding
        $dc = DB::selectOne(
            "SELECT atttypmod FROM pg_attribute
             WHERE attrelid = 'document_chunks'::regclass AND attname = 'embedding'"
        );

        if ($dc && $dc->atttypmod > 0) {
            // Drop HNSW index first (it depends on the dimension constraint)
            DB::statement('DROP INDEX IF EXISTS document_chunks_embedding_idx');
            DB::statement('ALTER TABLE document_chunks ALTER COLUMN embedding TYPE vector');
        }

        // Remove dimension constraint from embedding_cache.embedding
        $ec = DB::selectOne(
            "SELECT atttypmod FROM pg_attribute
             WHERE attrelid = 'embedding_cache'::regclass AND attname = 'embedding'"
        );

        if ($ec && $ec->atttypmod > 0) {
            DB::statement('ALTER TABLE embedding_cache ALTER COLUMN embedding TYPE vector');
        }
    }

    public function down(): void
    {
        // No-op: re-adding a dimension constraint would require knowing
        // which model was active, and the unconstrained type is strictly
        // more flexible.
    }
};
