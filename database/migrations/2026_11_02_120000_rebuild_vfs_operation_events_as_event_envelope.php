<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild `vfs_operation_events` around the CER event envelope (VfsEvent).
 *
 * No compatibility: OpenCompany has no users and migrations will be squashed,
 * so the old tool-call-only shape is dropped instead of migrated.
 *
 * - `id` is a monotonic surrogate key; the change feed pages on
 *   (occurred_at, id). `event_id` is the envelope id.
 * - `idempotency_key` is unique: read aggregation (one row per run or
 *   agent-minute and path) and repeated idempotent writes rely on it.
 * - `committed` marks committed writes so the change feed query is one index
 *   range scan per workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vfs_operation_events');

        Schema::create('vfs_operation_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->uuid('workspace_id');
            $table->string('type', 64);
            $table->string('source', 16)->default('vfs');
            $table->string('actor_type', 16)->default('agent');
            $table->string('agent_id')->nullable()->index();
            $table->string('surface', 32);
            $table->string('run_id')->nullable()->index();
            $table->string('causality_id')->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('operation', 64);
            $table->string('effect', 16)->nullable();
            $table->string('decision', 24);
            $table->string('action_class', 24)->nullable();
            $table->text('path')->nullable();
            $table->text('canonical_path')->nullable();
            $table->string('backend_type', 64)->nullable();
            $table->string('backend_id')->nullable();
            $table->string('approval_id')->nullable()->index();
            $table->string('before_version', 128)->nullable();
            $table->string('after_version', 128)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('committed')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->timestamps();

            $table->index(['workspace_id', 'committed', 'occurred_at', 'id'], 'vfs_events_change_feed_idx');
            $table->index(['workspace_id', 'type', 'occurred_at'], 'vfs_events_type_idx');
            $table->index(['backend_type', 'backend_id'], 'vfs_events_backend_idx');

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vfs_operation_events');
    }
};
