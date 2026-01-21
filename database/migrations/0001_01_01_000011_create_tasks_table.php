<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['backlog', 'in_progress', 'done'])->default('backlog');
            $table->string('assignee_id');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->decimal('cost', 12, 2)->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->string('channel_id')->nullable();
            $table->integer('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('assignee_id')->references('id')->on('users');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
