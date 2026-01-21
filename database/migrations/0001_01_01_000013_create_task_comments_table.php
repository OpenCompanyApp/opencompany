<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('task_id');
            $table->string('author_id');
            $table->text('content');
            $table->string('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users');
        });

        // Add self-referential foreign key after table creation
        Schema::table('task_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('task_comments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
