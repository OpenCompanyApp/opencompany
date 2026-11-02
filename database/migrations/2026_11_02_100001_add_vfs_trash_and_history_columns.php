<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trash (soft delete) and history attribution for VFS-editable records.
 *
 * Governance model: "delete means trash" (restorable, purged later by a
 * retention job that is not part of the VFS). Documents, table rows, list
 * items and workspace files therefore gain `deleted_at` + `deleted_by`; the
 * models use SoftDeletes so trashed records leave every listing and search
 * through Eloquent's global scope.
 *
 * `document_versions` gains the change-set attribution every agent write must
 * carry (run id, before/after version tokens) and a unique version number so
 * concurrent snapshots cannot share one number. `workspace_files.content_hash`
 * caches sha256(bytes) written through the VFS so listing a folder does not
 * read every file to compute its version token.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The squashed baseline already gives some of these tables
        // `deleted_at` (Trashable models); add only what is missing.
        foreach (['documents', 'data_table_rows', 'list_items', 'workspace_files'] as $tableName) {
            $hasDeletedAt = Schema::hasColumn($tableName, 'deleted_at');
            $hasDeletedBy = Schema::hasColumn($tableName, 'deleted_by');
            $hasIndex = Schema::hasIndex($tableName, ['deleted_at']);
            Schema::table($tableName, function (Blueprint $table) use ($hasDeletedAt, $hasDeletedBy, $hasIndex): void {
                if (! $hasDeletedAt) {
                    $table->softDeletes();
                }
                if (! $hasDeletedBy) {
                    $table->string('deleted_by')->nullable();
                }
                if (! $hasIndex) {
                    $table->index('deleted_at');
                }
            });
        }

        // The archive-friendly baseline migration (2026_11_02_000007) already
        // adds workspace_files.content_hash with the same meaning
        // (sha256 of the bytes); the VFS reuses it.
        if (! Schema::hasColumn('workspace_files', 'content_hash')) {
            Schema::table('workspace_files', function (Blueprint $table): void {
                $table->string('content_hash', 64)->nullable();
            });
        }

        Schema::table('document_versions', function (Blueprint $table): void {
            $table->string('run_id')->nullable();
            $table->string('before_version', 64)->nullable();
            $table->string('after_version', 64)->nullable();
            $table->unique(['document_id', 'version_number'], 'document_versions_document_number_unique');
            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table): void {
            $table->dropUnique('document_versions_document_number_unique');
            $table->dropIndex(['run_id']);
            $table->dropColumn(['run_id', 'before_version', 'after_version']);
        });


        // Only workspace_files' trash columns and deleted_by are owned here;
        // the baseline owns deleted_at on the Trashable tables.
        foreach (['documents', 'data_table_rows', 'list_items', 'workspace_files'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropColumn('deleted_by');
                if ($tableName === 'workspace_files') {
                    $table->dropIndex(['deleted_at']);
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
