<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `/workspace/changes.ndjson` feed pages on the auto-increment `id` only.
 *
 * It used to page on (occurred_at, id), but `occurred_at` is assigned in PHP
 * before the insert, so an event that is built first and inserted after a
 * later event was already delivered would sort behind the saved cursor and be
 * skipped forever. The insert-assigned id is the delivery order; the index
 * follows the query (workspace, committed, id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vfs_operation_events', function (Blueprint $table): void {
            $table->dropIndex('vfs_events_change_feed_idx');
            $table->index(['workspace_id', 'committed', 'id'], 'vfs_events_change_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vfs_operation_events', function (Blueprint $table): void {
            $table->dropIndex('vfs_events_change_feed_idx');
            $table->index(['workspace_id', 'committed', 'occurred_at', 'id'], 'vfs_events_change_feed_idx');
        });
    }
};
