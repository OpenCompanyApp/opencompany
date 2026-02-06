<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_config_id');

            // Behavior control
            $table->string('behavior_mode')->default('supervised'); // autonomous, supervised, strict
            $table->string('security_mode')->default('allowlist'); // deny, allowlist, full
            $table->string('ask_mode')->default('on-miss'); // off, on-miss, always

            // Cost & token management
            $table->decimal('cost_limit_daily', 10, 2)->nullable();
            $table->integer('max_tokens_per_request')->default(4096);
            $table->integer('reserve_tokens')->default(16384);
            $table->integer('reserve_tokens_floor')->default(20000);
            $table->integer('keep_recent_tokens')->default(20000);
            $table->integer('soft_threshold_tokens')->default(4000);

            // Pruning & cleanup
            $table->integer('pruning_ttl_minutes')->default(5);
            $table->boolean('auto_allow_skills')->default(true);
            $table->json('reset_policy')->nullable();

            $table->timestamps();

            $table->foreign('agent_config_id')->references('id')->on('agent_configurations')->cascadeOnDelete();
            $table->unique('agent_config_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_settings');
    }
};
