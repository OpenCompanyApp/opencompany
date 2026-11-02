<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace Automations with Routines (docs/planning/connections-events-
 * routines.md, "Routines" and "What gets deleted").
 *
 * OpenCompany has no users and the migration squash follows, so this is a
 * plain forward replacement rather than a data-preserving rename:
 *  - `automations` and `automation_script_revisions` become `routines` and
 *    `routine_script_revisions` with the routine shape (kind, instructions,
 *    mode, notify, version, built-in key);
 *  - `list_automation_rules` is dropped (record-change triggers replace it);
 *  - `integration_webhooks` is dropped (the inbound-webhook connection and
 *    connection event capabilities replace it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('automation_script_revisions');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('list_automation_rules');
        Schema::dropIfExists('integration_webhooks');

        Schema::create('routines', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->text('description')->nullable();
            // agent (LLM, instructions) | script (mruby, no LLM) | builtin
            // (code-defined, no LLM, identified by built_in_key).
            $table->string('kind', 16)->default('agent');
            // Code-defined built-in routine key, e.g. telegram.digest. Unique
            // per workspace so the tick can provision built-ins idempotently.
            $table->string('built_in_key', 64)->nullable();
            // Built-in routines have no executing agent or human author.
            $table->string('agent_id')->nullable();
            $table->text('instructions')->nullable();
            $table->longText('script')->nullable();
            $table->string('script_runtime')->nullable();
            $table->string('script_digest', 64)->nullable();
            $table->string('script_engine_digest', 64)->nullable();
            $table->timestamp('script_validated_at')->nullable();
            // Increments whenever instructions or script change.
            $table->unsignedInteger('version')->default(1);
            // read_only | act. Stored now; enforced at tool registration in Phase 3.
            $table->string('mode', 16)->default('act');
            // always | if_new | on_failure.
            $table->string('notify', 16)->default('always');
            // Hash of the last output posted to the channel (notify=if_new).
            $table->string('last_output_digest', 64)->nullable();
            // Schedule trigger.
            $table->string('cron_expression');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            // Output channel.
            $table->string('channel_id')->nullable();
            $table->boolean('keep_history')->default(true);
            $table->string('created_by_id')->nullable();
            $table->json('last_result')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['is_active', 'next_run_at']);
            $table->index(['workspace_id', 'created_at', 'id'], 'vfs_routines_page_idx');
            $table->unique(['workspace_id', 'built_in_key']);
        });

        Schema::create('routine_script_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('routine_id')->index();
            $table->string('runtime')->nullable();
            $table->longText('source');
            $table->string('source_digest', 64);
            $table->string('engine_digest', 64)->nullable();
            $table->string('status');
            $table->timestamp('created_at');
            // Revisions are append-only and survive routine deletion.
            $table->unique(['routine_id', 'source_digest', 'engine_digest', 'status'], 'routine_script_revision_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_script_revisions');
        Schema::dropIfExists('routines');
    }
};
