<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add deterministic lexical acceleration for VFS path/title/name discovery.
     *
     * VFS remains separate from embeddings. These indexes only speed up
     * Postgres substring/fuzzy-ish text filters used by find/search planning.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->ginTrgm('documents', 'title', 'documents_title_trgm_idx');
        $this->ginTrgm('documents', 'content', 'documents_content_trgm_idx');
        $this->ginTrgm('workspace_files', 'name', 'workspace_files_name_trgm_idx');
        $this->ginTrgm('workspace_files', 'description', 'workspace_files_description_trgm_idx');
        $this->ginTrgm('tasks', 'title', 'tasks_title_trgm_idx');
        $this->ginTrgm('tasks', 'description', 'tasks_description_trgm_idx');
        $this->ginTrgm('list_items', 'title', 'list_items_title_trgm_idx');
        $this->ginTrgm('list_items', 'description', 'list_items_description_trgm_idx');
        $this->ginTrgm('channels', 'name', 'channels_name_trgm_idx');
        $this->ginTrgm('messages', 'content', 'messages_content_trgm_idx');
        $this->ginTrgm('data_tables', 'name', 'data_tables_name_trgm_idx');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'documents_title_trgm_idx',
            'documents_content_trgm_idx',
            'workspace_files_name_trgm_idx',
            'workspace_files_description_trgm_idx',
            'tasks_title_trgm_idx',
            'tasks_description_trgm_idx',
            'list_items_title_trgm_idx',
            'list_items_description_trgm_idx',
            'channels_name_trgm_idx',
            'messages_content_trgm_idx',
            'data_tables_name_trgm_idx',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }

    private function ginTrgm(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::statement("CREATE INDEX IF NOT EXISTS {$index} ON {$table} USING gin ({$column} gin_trgm_ops)");
    }
};
