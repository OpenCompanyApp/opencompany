<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_automations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('agent_id');
            $table->text('prompt');
            $table->string('cron_expression');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('channel_id')->nullable();
            $table->string('created_by_id');
            $table->json('last_result')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_automations');
    }
};
