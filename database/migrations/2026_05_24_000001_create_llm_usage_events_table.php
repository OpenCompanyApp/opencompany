<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_usage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('agent_id')->nullable()->index();
            $table->string('task_id')->nullable()->index();
            $table->string('purpose')->index();
            $table->string('status')->default('completed')->index();
            $table->string('requested_provider')->nullable()->index();
            $table->string('requested_model')->nullable()->index();
            $table->string('resolved_provider')->nullable()->index();
            $table->string('resolved_model')->nullable()->index();
            $table->string('provider_generation_id')->nullable()->index();
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('cache_read_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->unsignedBigInteger('reasoning_tokens')->default(0);
            $table->unsignedBigInteger('tool_calls_count')->default(0);
            $table->unsignedBigInteger('generation_time_ms')->nullable();
            $table->decimal('estimated_cost_usd', 14, 8)->nullable();
            $table->decimal('actual_cost_usd', 14, 8)->nullable();
            $table->string('cost_source')->default('catalog_estimate');
            $table->json('raw_usage')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usage_events');
    }
};
