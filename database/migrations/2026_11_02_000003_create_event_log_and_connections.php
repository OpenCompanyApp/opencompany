<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline for the Connections, Events and Routines model
 * (docs/planning/connections-events-routines.md): one append-only workspace
 * event log with the standard envelope and causality chain, and Connections
 * whose secrets live in a vault and are used only through the credential
 * broker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            // Namespaced type, e.g. task.assigned, message.posted, time.tick.
            $table->string('type', 120);
            // connection | channel | internal | scheduler (+ optional ref).
            $table->string('source', 120);
            $table->string('source_ref')->nullable();
            // VFS path or record reference.
            $table->string('subject', 500)->nullable();
            // human | agent | connection | system.
            $table->string('actor_type', 20);
            $table->string('actor_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->string('idempotency_key')->nullable();
            // Always untrusted data when it reaches a prompt.
            $table->json('payload')->nullable();
            // Loop protection: root of the chain and hops from it.
            $table->uuid('causality_id');
            $table->uuid('parent_event_id')->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->uuid('run_id')->nullable();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'idempotency_key']);
            $table->index(['workspace_id', 'type', 'recorded_at']);
            $table->index('causality_id');
        });

        Schema::create('connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            // Connector kind, e.g. mcp, google_drive, inbound_webhook.
            $table->string('kind', 64);
            $table->string('name');
            // personal (a human's own account) | shared (workspace credential).
            $table->string('ownership', 16);
            $table->string('owner_user_id')->nullable();
            $table->string('status', 24)->default('active');
            // Non-secret settings only; secrets live in vault_secrets.
            $table->json('settings')->nullable();
            $table->string('created_by_id')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['workspace_id', 'kind']);
        });

        Schema::create('vault_secrets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('connection_id');
            $table->string('name', 120);
            // Encrypted with the vault key; never serialized to clients.
            $table->text('ciphertext');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('connection_id')->references('id')->on('connections')->cascadeOnDelete();
            $table->unique(['connection_id', 'name']);
        });

        Schema::create('connection_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->string('agent_id');
            // read | act
            $table->string('mode', 8);
            $table->string('granted_by_id')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('connections')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['connection_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_grants');
        Schema::dropIfExists('vault_secrets');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('workspace_events');
    }
};
