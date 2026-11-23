<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only authorization decision ledger (GIN-1204; ROADMAP Phase 0 P0
 * item 9). One row per security-relevant decision or security change.
 *
 * Tamper evidence: rows form a per-workspace hash chain. `sequence` is
 * gap-free per workspace (unique with workspace_id, so concurrent appends
 * cannot fork the chain), `prev_hash` is the previous row's `entry_hash`,
 * and `entry_hash` covers every other column. AuthorizationLedger::verify()
 * recomputes the chain. The model refuses updates and deletes.
 *
 * String (ULID) keys keep the table archive-friendly (pillar 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('workspace_id');
            $table->unsignedBigInteger('sequence');
            $table->string('kind', 32);
            $table->string('action', 128);
            $table->string('effect', 32);
            $table->string('reason_code', 128);
            $table->text('reason')->nullable();
            $table->string('actor_type', 32);
            $table->string('actor_id')->nullable();
            $table->string('on_behalf_of_id')->nullable();
            $table->string('resource_type', 64)->nullable();
            $table->string('resource_id')->nullable();
            $table->string('policy_version', 64);
            $table->string('policy_hash', 64);
            $table->string('correlation_id', 128)->nullable();
            $table->string('run_id')->nullable();
            $table->json('details')->nullable();
            $table->json('trace')->nullable();
            $table->dateTime('occurred_at');
            $table->string('prev_hash', 64)->nullable();
            $table->string('entry_hash', 64);

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'sequence']);
            $table->index(['workspace_id', 'action', 'occurred_at']);
            $table->index(['workspace_id', 'actor_id', 'occurred_at']);
            $table->index(['workspace_id', 'effect', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_ledger_entries');
    }
};
