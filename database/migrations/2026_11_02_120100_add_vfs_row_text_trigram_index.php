<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trigram index over table-row JSON rendered as text, so VFS text search can
 * find matching rows on any page (FindsVfsSourceTextCandidates::rowCandidates
 * queries `(data::text) ILIKE ?`; the expression must match this index).
 * PostgreSQL only; other drivers fall back to a scan of the workspace's rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS data_table_rows_data_text_trgm_idx ON data_table_rows USING gin ((data::text) gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS data_table_rows_data_text_trgm_idx');
    }
};
