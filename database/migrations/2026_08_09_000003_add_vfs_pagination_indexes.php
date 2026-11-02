<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes in the same prefix and sort order used by VFS keyset pages.
     *
     * Cursor correctness does not depend on these indexes, but traversing a
     * large workspace must not repeatedly sort or scan an entire tenant table.
     * The explicit short names remain portable under PostgreSQL's identifier
     * limit and make the rollback contract unambiguous.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->index(['workspace_id', 'is_system', 'is_folder', 'title', 'id'], 'vfs_docs_all_page_idx');
            $table->index(['workspace_id', 'parent_id', 'is_system', 'is_folder', 'title', 'id'], 'vfs_docs_child_page_idx');
        });
        Schema::table('workspace_files', function (Blueprint $table): void {
            $table->index(['workspace_id', 'is_folder', 'name', 'id'], 'vfs_files_all_page_idx');
            $table->index(['workspace_id', 'parent_id', 'is_folder', 'name', 'id'], 'vfs_files_child_page_idx');
        });
        Schema::table('tasks', function (Blueprint $table): void {
            $table->index(['workspace_id', 'updated_at', 'id'], 'vfs_tasks_recent_page_idx');
            $table->index(['workspace_id', 'status', 'updated_at', 'id'], 'vfs_tasks_status_page_idx');
            $table->index(['workspace_id', 'agent_id', 'updated_at', 'id'], 'vfs_tasks_agent_page_idx');
            $table->index(['workspace_id', 'agent_id', 'status', 'updated_at', 'id'], 'vfs_tasks_agent_status_page_idx');
        });
        Schema::table('list_items', function (Blueprint $table): void {
            $table->index(['workspace_id', 'parent_id', 'is_folder', 'updated_at', 'id'], 'vfs_lists_parent_page_idx');
            $table->index(['workspace_id', 'status', 'updated_at', 'id'], 'vfs_lists_status_page_idx');
            $table->index(['workspace_id', 'assignee_id', 'updated_at', 'id'], 'vfs_lists_assignee_page_idx');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['workspace_id', 'type', 'name', 'id'], 'vfs_agents_name_page_idx');
        });
        Schema::table('channels', function (Blueprint $table): void {
            $table->index(['workspace_id', 'name', 'id'], 'vfs_channels_name_page_idx');
        });
        Schema::table('messages', function (Blueprint $table): void {
            $table->index(['channel_id', 'timestamp', 'id'], 'vfs_messages_time_page_idx');
            $table->index(['channel_id', 'created_at', 'id'], 'vfs_messages_fallback_page_idx');
        });
        Schema::table('data_tables', function (Blueprint $table): void {
            $table->index(['workspace_id', 'name', 'id'], 'vfs_tables_name_page_idx');
        });
        Schema::table('data_table_rows', function (Blueprint $table): void {
            $table->index(['table_id', 'updated_at', 'id'], 'vfs_table_rows_page_idx');
        });
        Schema::table('data_table_views', function (Blueprint $table): void {
            $table->index(['table_id', 'created_at', 'id'], 'vfs_table_views_page_idx');
        });
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->index(['requester_id', 'created_at', 'id'], 'vfs_approvals_page_idx');
        });
        Schema::table('automations', function (Blueprint $table): void {
            $table->index(['workspace_id', 'created_at', 'id'], 'vfs_automations_page_idx');
        });
    }

    public function down(): void
    {
        Schema::table('automations', fn (Blueprint $table) => $table->dropIndex('vfs_automations_page_idx'));
        Schema::table('approval_requests', fn (Blueprint $table) => $table->dropIndex('vfs_approvals_page_idx'));
        Schema::table('data_table_views', fn (Blueprint $table) => $table->dropIndex('vfs_table_views_page_idx'));
        Schema::table('data_table_rows', fn (Blueprint $table) => $table->dropIndex('vfs_table_rows_page_idx'));
        Schema::table('data_tables', fn (Blueprint $table) => $table->dropIndex('vfs_tables_name_page_idx'));
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('vfs_messages_time_page_idx');
            $table->dropIndex('vfs_messages_fallback_page_idx');
        });
        Schema::table('channels', fn (Blueprint $table) => $table->dropIndex('vfs_channels_name_page_idx'));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('vfs_agents_name_page_idx'));
        Schema::table('list_items', function (Blueprint $table): void {
            $table->dropIndex('vfs_lists_parent_page_idx');
            $table->dropIndex('vfs_lists_status_page_idx');
            $table->dropIndex('vfs_lists_assignee_page_idx');
        });
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('vfs_tasks_recent_page_idx');
            $table->dropIndex('vfs_tasks_status_page_idx');
            $table->dropIndex('vfs_tasks_agent_page_idx');
            $table->dropIndex('vfs_tasks_agent_status_page_idx');
        });
        Schema::table('workspace_files', function (Blueprint $table): void {
            $table->dropIndex('vfs_files_all_page_idx');
            $table->dropIndex('vfs_files_child_page_idx');
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('vfs_docs_all_page_idx');
            $table->dropIndex('vfs_docs_child_page_idx');
        });
    }
};
