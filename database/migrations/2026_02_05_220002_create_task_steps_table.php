<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the task_steps table for tracking agent work progress.
     */
    public function up(): void
    {
        Schema::create('task_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('status')->default('pending'); // pending, in_progress, completed, skipped
            $table->string('step_type')->default('action'); // action, decision, approval, sub_task, message
            $table->json('metadata')->nullable(); // Tool calls, API responses, etc.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('step_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_steps');
    }
};
