<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy model (Product Direction pillar 5): agent behaviour modes are deleted.
 * Writes, external actions and untyped tools need approval by default; admins
 * grant scoped, exact-match exceptions stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('agent_id')->index();
            // Exact tool slug; never a pattern.
            $table->string('tool_slug');
            // once | run | task | until | always
            $table->string('scope', 16);
            $table->uuid('run_id')->nullable();
            $table->uuid('task_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('granted_by_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'tool_slug']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
        });

        if (Schema::hasColumn('users', 'behavior_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('behavior_mode');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_grants');
        Schema::table('users', function (Blueprint $table) {
            $table->string('behavior_mode')->nullable();
        });
    }
};
