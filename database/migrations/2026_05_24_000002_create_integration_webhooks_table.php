<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('target_type', 32);
            $table->string('target_id')->nullable();
            $table->text('secret');
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('call_count')->default(0);
            $table->text('last_payload')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhooks');
    }
};
