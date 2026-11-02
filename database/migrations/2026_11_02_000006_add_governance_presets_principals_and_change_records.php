<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Governance revision (Product Direction pillar 5, docs/architecture/governance-model.md):
 *
 * - `workspaces.governance_preset`: personal | team | company, or null for the
 *   derived default (personal for single-member workspaces, team otherwise).
 *   Presets set defaults only; the invariants live in code.
 * - `agent_runs.on_behalf_of_user_id`: the human principal whose access is
 *   intersected with the agent's grants (chat/task requester, routine owner,
 *   inherited through delegation). Re-checked at execution and replay.
 * - `change_records`: per-run before/after history of reversible internal
 *   edits, so "revert this run" can undo them while values are unchanged since.
 * - `deleted_at` on documents, data table rows and list items (workspace
 *   files keep hard delete, classed as purge, until they get a trash).
 *   Delete moves to trash; purge is a separate destructive action.
 */
return new class extends Migration
{
    private const TRASHABLE = ['documents', 'data_table_rows', 'list_items'];

    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('governance_preset', 16)->nullable();
        });

        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->string('on_behalf_of_user_id')->nullable()->index();
        });

        Schema::create('change_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->uuid('run_id')->nullable()->index();
            $table->string('agent_id')->nullable();
            $table->string('subject_type', 64);
            $table->string('subject_id');
            $table->string('operation', 16); // created | updated | deleted
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->string('reverted_by_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });

        foreach (self::TRASHABLE as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TRASHABLE as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
        Schema::dropIfExists('change_records');
        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->dropIndex(['on_behalf_of_user_id']);
            $table->dropColumn('on_behalf_of_user_id');
        });
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('governance_preset');
        });
    }
};
