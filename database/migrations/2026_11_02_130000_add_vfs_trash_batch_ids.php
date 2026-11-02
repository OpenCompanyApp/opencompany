<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A real trash-batch identifier for every soft-deletable VFS record.
 *
 * Restoring a trashed folder must bring back exactly the objects trashed in
 * that same operation. `deleted_at` cannot identify a batch: it has second
 * precision, so an object trashed on its own a moment before its folder would
 * share the folder's timestamp and be resurrected with it. Every trash
 * operation therefore stamps one fresh `trash_batch_id` on the root and all
 * descendants it trashes; restore selects descendants by that id only.
 *
 * Null for live rows and cleared on restore. Table rows are leaves (a row
 * trash is always a batch of one), so they need no batch id.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['documents', 'list_items', 'workspace_files'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('trash_batch_id', 36)->nullable();
                $table->index('trash_batch_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['documents', 'list_items', 'workspace_files'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['trash_batch_id']);
                $table->dropColumn('trash_batch_id');
            });
        }
    }
};
