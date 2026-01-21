<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('document_id');
            $table->string('author_id');
            $table->text('content');
            $table->string('parent_id')->nullable();
            $table->boolean('resolved')->default(false);
            $table->string('resolved_by_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('resolved_by_id')->references('id')->on('users')->onDelete('set null');
        });

        // Add self-referential foreign key after table creation
        Schema::table('document_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('document_comments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
