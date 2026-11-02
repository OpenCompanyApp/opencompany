<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the stale `tasks_status_check` constraint on `list_items` (PostgreSQL).
 *
 * `list_items` was renamed from an early `tasks` table whose status was a
 * fixed enum (backlog, in_progress, done). List statuses are now
 * workspace-defined rows in `list_statuses` (slugs such as `todo`), and the
 * domain tools and the VFS write any status slug of the workspace, so the
 * enum CHECK contradicts the data model and rejects valid custom statuses.
 * The SQLite baseline never carried it (the table rebuild dropped it); this
 * makes both engines agree. Status validity is enforced by the application
 * against `list_statuses` (CreateListItem, MutatesVfsListItems).
 *
 * The priority CHECK (low, medium, high, urgent) is kept: priorities are a
 * fixed application enum, validated identically in code on both engines.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE list_items DROP CONSTRAINT IF EXISTS tasks_status_check');
        }
    }

    public function down(): void
    {
        // Intentionally not restored: the enum contradicts workspace-defined statuses.
    }
};
