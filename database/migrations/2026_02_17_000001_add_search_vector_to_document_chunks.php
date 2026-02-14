<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE document_chunks ADD COLUMN search_vector tsvector');

        DB::statement('CREATE INDEX document_chunks_search_vector_idx ON document_chunks USING GIN (search_vector)');

        DB::statement("
            CREATE OR REPLACE FUNCTION document_chunks_search_vector_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector := to_tsvector('english', COALESCE(NEW.content, ''));
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER document_chunks_search_vector_trigger
            BEFORE INSERT OR UPDATE OF content ON document_chunks
            FOR EACH ROW EXECUTE FUNCTION document_chunks_search_vector_update();
        ");

        DB::statement("UPDATE document_chunks SET search_vector = to_tsvector('english', COALESCE(content, ''))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS document_chunks_search_vector_trigger ON document_chunks');
        DB::statement('DROP FUNCTION IF EXISTS document_chunks_search_vector_update()');
        DB::statement('DROP INDEX IF EXISTS document_chunks_search_vector_idx');
        DB::statement('ALTER TABLE document_chunks DROP COLUMN IF EXISTS search_vector');
    }
};
