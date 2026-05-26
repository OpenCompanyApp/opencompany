<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vfs_operation_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('agent_id')->index();
            $table->string('surface', 32)->index();
            $table->string('operation', 64)->index();
            $table->text('path')->nullable();
            $table->text('cwd')->nullable();
            $table->boolean('success')->default(true)->index();
            $table->string('error_code')->nullable()->index();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vfs_operation_events');
    }
};
