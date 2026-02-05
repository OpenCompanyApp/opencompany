<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the new tasks table (cases-style work tracking).
     * This is for discrete work items that agents process.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('custom'); // ticket, request, analysis, content, research, custom
            $table->string('status')->default('pending'); // pending, active, paused, completed, failed, cancelled
            $table->string('priority')->default('normal'); // low, normal, high, urgent

            // Assignments (using string to match users table)
            $table->string('agent_id')->nullable();
            $table->string('requester_id');

            // Relationships (using string to match related tables)
            $table->string('channel_id')->nullable();
            $table->string('list_item_id')->nullable(); // Link to list item if created from one
            $table->uuid('parent_task_id')->nullable(); // For sub-tasks

            // Data
            $table->json('context')->nullable(); // Relevant data/files for the task
            $table->json('result')->nullable(); // Output/deliverables when complete

            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
            $table->foreign('list_item_id')->references('id')->on('list_items')->nullOnDelete();

            // Indexes
            $table->index('status');
            $table->index('type');
            $table->index('priority');
            $table->index(['agent_id', 'status']);
        });

        // Add self-referential foreign key after table creation
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('parent_task_id')->references('id')->on('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
        });
        Schema::dropIfExists('tasks');
    }
};
