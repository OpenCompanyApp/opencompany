<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_title');
            $table->text('default_description')->nullable();
            $table->enum('default_priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->string('default_assignee_id')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->json('tags')->nullable();
            $table->string('created_by_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('default_assignee_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
