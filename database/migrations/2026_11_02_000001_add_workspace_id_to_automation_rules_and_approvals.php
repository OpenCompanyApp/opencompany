<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give automation rules and approval requests a first-class tenant column.
 *
 * Both tables were previously scoped (or not scoped at all) through optional
 * relations: automation rules through an optional list template, approvals
 * through an optional channel. Rows without that relation were either visible
 * to every workspace (automation rules, cross-workspace IDOR) or invisible to
 * every workspace (channel-less approvals such as ContactAgent access
 * requests). A direct `workspace_id` makes `forWorkspace()` the single scoping
 * rule for both.
 *
 * Backfill order (first non-null wins) and why:
 *  - automation rules: template workspace -> creator agent workspace ->
 *    creator human's ONLY workspace membership.
 *  - approvals: channel workspace -> requester agent workspace ->
 *    requester human's ONLY workspace membership.
 * Humans with several memberships are deliberately not guessed: a wrong guess
 * would leak the row into another tenant. Rows that stay NULL are unreachable
 * through the scoped APIs (fail closed) and the column therefore stays
 * nullable. ApprovalExecutionService refuses to execute approvals whose
 * workspace_id mismatches the requesting agent, so the column name must remain
 * exactly `workspace_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_automation_rules', function (Blueprint $table): void {
            $table->uuid('workspace_id')->nullable()->after('id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'is_active']);
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->uuid('workspace_id')->nullable()->after('id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'status', 'created_at']);
        });

        $this->backfill('list_automation_rules', 'list_template_id', 'list_templates', 'created_by_id');
        $this->backfill('approval_requests', 'channel_id', 'channels', 'requester_id');
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id']);
            $table->dropIndex(['workspace_id', 'status', 'created_at']);
            $table->dropColumn('workspace_id');
        });

        Schema::table('list_automation_rules', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id']);
            $table->dropIndex(['workspace_id', 'is_active']);
            $table->dropColumn('workspace_id');
        });
    }

    /**
     * Fill `workspace_id` from the owning relation, then the actor, using
     * correlated subqueries that work on SQLite, MySQL and PostgreSQL.
     */
    private function backfill(string $table, string $relationColumn, string $relationTable, string $actorColumn): void
    {
        DB::table($table)
            ->whereNull('workspace_id')
            ->whereNotNull($relationColumn)
            ->update([
                'workspace_id' => DB::raw("(SELECT {$relationTable}.workspace_id FROM {$relationTable} WHERE {$relationTable}.id = {$table}.{$relationColumn})"),
            ]);

        DB::table($table)
            ->whereNull('workspace_id')
            ->update([
                'workspace_id' => DB::raw("(SELECT users.workspace_id FROM users WHERE users.id = {$table}.{$actorColumn} AND users.type = 'agent')"),
            ]);

        // Single-membership humans only; see class docblock for why.
        DB::table($table)
            ->whereNull('workspace_id')
            ->update([
                'workspace_id' => DB::raw(
                    "(SELECT MIN(workspace_members.workspace_id) FROM workspace_members WHERE workspace_members.user_id = {$table}.{$actorColumn} "
                    ."AND (SELECT COUNT(*) FROM workspace_members wm2 WHERE wm2.user_id = {$table}.{$actorColumn}) = 1)"
                ),
            ]);
    }
};
