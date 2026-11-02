<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembered results of VFS mutations that carried an `idempotency_key`.
 *
 * Keyed by (agent, key) for 24 hours: a retried call (for example after a
 * timeout) returns the stored result instead of mutating twice. Expired rows
 * are ignored on read and may be pruned by any housekeeping job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vfs_idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('workspace_id');
            $table->string('agent_id');
            $table->string('idempotency_key', 191);
            $table->string('operation', 32);
            $table->text('path');
            // Null while the first call is still running (claims the key).
            $table->json('result')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['agent_id', 'idempotency_key'], 'vfs_idempotency_keys_agent_key_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vfs_idempotency_keys');
    }
};
