<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->enum('type', ['message', 'task_completed', 'task_started', 'agent_spawned', 'approval_needed', 'approval_granted', 'error']);
            $table->text('description');
            $table->string('actor_id');
            $table->json('metadata')->nullable();
            $table->timestamp('timestamp');

            $table->foreign('actor_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
