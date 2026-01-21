<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_automation_rules', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_type', ['task_created', 'task_completed', 'task_assigned', 'approval_granted', 'approval_rejected', 'schedule']);
            $table->json('trigger_conditions')->nullable();
            $table->enum('action_type', ['create_task', 'assign_task', 'send_notification', 'update_task', 'spawn_agent']);
            $table->json('action_config')->nullable();
            $table->string('template_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->string('created_by_id');
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('task_templates')->onDelete('set null');
            $table->foreign('created_by_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_automation_rules');
    }
};
