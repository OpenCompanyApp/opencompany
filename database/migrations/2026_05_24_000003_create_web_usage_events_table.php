<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_usage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->nullable()->index();
            $table->string('agent_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('capability', 32)->index();
            $table->string('provider')->index();
            $table->string('request_hash')->index();
            $table->text('query')->nullable();
            $table->text('url')->nullable();
            $table->text('final_url')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedInteger('result_count')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->boolean('cache_hit')->default(false)->index();
            $table->boolean('success')->default(true)->index();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_usage_events');
    }
};
